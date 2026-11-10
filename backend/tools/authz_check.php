<?php
/**
 * Exercita a matriz de permissões e o CSRF por HTTP, como um cliente real.
 *
 *   php backend/tools/authz_check.php --force [url-base]
 *
 * Faz login de verdade (formulário + token), guarda o cookie de sessão e chama
 * cada endpoint como gerente, como funcionário e sem sessão, comparando o
 * status HTTP com o esperado.
 *
 * Depende do seed: php backend/scripts/seed.php
 *
 * SÓ EM BANCO DE TESTE. O script escreve no banco: cria pedidos na loja
 * "teste", marca-os como pagos por alguns instantes e troca o vendedor de um
 * deles, direto por SQL. Tudo é desfeito no fim (bloco finally, e também se o
 * script morrer no meio por exceção, exit ou erro fatal), mas um Ctrl+C ou a
 * máquina desligando no meio deixam um pedido "pago" que nunca existiu — e
 * isso entra no faturamento, no BI e na meta. Por isso exige --force.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

// Domínio dos e-mails do seed (gerente@…, funcionario@…). Um banco montado
// antes do seed atual pode ter outro, como loja.teste: SEED_EMAIL_DOMAIN=loja.teste
$dominio = getenv('SEED_EMAIL_DOMAIN') ?: 'loja.test';
$emailGerente = 'gerente@' . $dominio;
$emailFunc = 'funcionario@' . $dominio;

$argumentos = array_slice($argv, 1);
$forcar = in_array('--force', $argumentos, true);
$urlInformada = array_values(array_filter($argumentos, static fn (string $a): bool => strncmp($a, '--', 2) !== 0))[0] ?? null;
if (!$forcar) {
    fwrite(STDERR, "ATENÇÃO: este script escreve no banco (cria pedidos e muda status por alguns instantes).\n"
        . "Rode só em banco de teste, com o seed aplicado:\n"
        . "  php backend/tools/authz_check.php --force [url-base]\n");
    exit(2);
}
$base = rtrim($urlInformada ?? (getenv('APP_URL') ?: 'http://localhost/plataform_stores/public'), '/');
$slug = 'teste';
$falhas = 0;
$total = 0;

/** Requisição HTTP guardando cookies num arquivo próprio de cada sessão. */
function http(string $metodo, string $url, ?string $jar, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if (!empty($opts['csrf'])) {
        $headers[] = 'X-CSRF-Token: ' . $opts['csrf'];
    }
    if (isset($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if (isset($opts['json'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
    } elseif (isset($opts['form'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
    }
    $corpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $codigo, 'body' => (string) $corpo];
}

/**
 * Faz login EXTRAINDO o formulário da página, como o navegador faria.
 *
 * A versão anterior montava o POST à mão, pegando o token do <meta> e
 * inventando os campos. Isso escondeu um estrago real: o csrf_field() tinha
 * sido inserido no meio da tag <form> (a regex `<form[^>]*>` parava no `>` do
 * `?>` de um `<?= base_url(...) ?>`), o formulário ficava malformado e sem
 * token, e o login pelo navegador estava quebrado — enquanto este teste
 * passava, porque nunca tocava no HTML do formulário.
 *
 * Agora só são enviados os campos que o formulário realmente oferece.
 */
function login(string $base, string $email, string $senha, string $jar): ?string
{
    @unlink($jar);
    $pagina = http('GET', $base . '/', $jar);

    if (!preg_match('#<form[^>]*action="([^"]*/login)"[^>]*>(.*?)</form>#s', $pagina['body'], $m)) {
        fwrite(STDERR, "Formulário de login não encontrado na home.\n");

        return null;
    }
    $action = $m[1];
    preg_match_all('#<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"#', $m[2], $inputs, PREG_SET_ORDER);
    $form = [];
    foreach ($inputs as $input) {
        $form[$input[1]] = $input[2];
    }
    if (!isset($form['_csrf']) || $form['_csrf'] === '') {
        fwrite(STDERR, "O formulário de login não traz o campo _csrf.\n");

        return null;
    }
    $form['email'] = $email;
    $form['password'] = $senha;

    $r = http('POST', $action, $jar, ['form' => $form]);
    if ($r['status'] !== 302) {
        return null;
    }
    // O login renova o token; pega o novo de uma página autenticada.
    $pagina = http('GET', $base . '/lojas', $jar);

    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $pagina['body'], $m) ? $m[1] : null;
}

$ok = static function (string $nome, bool $passou) use (&$falhas, &$total): void {
    $total++;
    if (!$passou) {
        $falhas++;
    }
    echo ($passou ? '  PASSA  ' : '  FALHA  ') . $nome . PHP_EOL;
};

$jarGerente = sys_get_temp_dir() . '/authz_gerente.txt';
$jarFunc = sys_get_temp_dir() . '/authz_func.txt';

$csrfGerente = login($base, $emailGerente, 'gerente123', $jarGerente);
$csrfFunc = login($base, $emailFunc, 'gerente123', $jarFunc);

if ($csrfGerente === null || $csrfFunc === null) {
    fwrite(STDERR, "Não foi possível autenticar. Rode: php backend/scripts/seed.php\n");
    exit(1);
}
echo PHP_EOL . 'Sessões abertas (gerente e funcionário).' . PHP_EOL;

// ------------------------------------------------------------- CSRF
echo PHP_EOL . 'CSRF' . PHP_EOL;
$r = http('POST', "{$base}/api/loja/{$slug}/vitrine-categories", $jarGerente, [
    'json' => ['name' => 'X', 'icon_key' => 'treino'],
]);
$ok('POST sem token é recusado (403+X-CSRF-Retry)', $r["status"] === 403);

$r = http('POST', "{$base}/api/loja/{$slug}/vitrine-categories", $jarGerente, [
    'json' => ['name' => 'X', 'icon_key' => 'treino'], 'csrf' => 'token-invalido',
]);
$ok('POST com token errado é recusado (403)', $r["status"] === 403);

$r = http('GET', "{$base}/api/loja/{$slug}/products", $jarGerente);
$ok('GET não exige token', $r['status'] === 200);

// -------------------------------------------------- matriz de permissões
// [rótulo, método, caminho, esperado gerente, esperado funcionário, esperado anônimo]
$casos = [
    ['ver produtos',        'GET',  "/api/loja/{$slug}/products",            200, 200, 401],
    ['ver estoque baixo',   'GET',  "/api/loja/{$slug}/products/low-stock",  200, 200, 401],
    // Histórico de estoque (quem moveu o quê) é só do gerente: store.stock.history.
    ['movimentos estoque',  'GET',  "/api/loja/{$slug}/stock-movements",     200, 403, 401],
    ['ver pedidos',         'GET',  "/api/loja/{$slug}/orders",              200, 200, 401],
    ['ver entregas',        'GET',  "/api/loja/{$slug}/orders/entregas",     200, 200, 401],
    ['caixa',               'GET',  "/api/loja/{$slug}/cash/status",         200, 200, 401],
    ['resumo do caixa',     'GET',  "/api/loja/{$slug}/cash/summary",        200, 200, 401],
    ['ver metas',           'GET',  "/api/loja/{$slug}/goals",               200, 200, 401],
    ['ver cargos',          'GET',  "/api/loja/{$slug}/roles",               200, 200, 401],
    ['banner da vitrine',   'GET',  "/api/loja/{$slug}/banner",              200, 403, 401],

    // Só gerente:
    ['Analyzing BI',        'GET',  "/api/loja/{$slug}/analyzing-bi",        200, 403, 401],
    ['listar equipe',       'GET',  "/api/loja/{$slug}/users",               200, 403, 401],
    ['config PIX',          'GET',  "/api/loja/{$slug}/pix-config",          200, 403, 401],
    ['pagamentos pendentes','GET',  "/api/loja/{$slug}/payments/pending",    200, 403, 401],

    // Só gerente: receita, clientes e estorno.
    ['indicadores do BI',   'GET',  "/api/loja/{$slug}/analyzing-bi/indicadores", 200, 403, 401],
    ['simulador de compra', 'GET',  "/api/loja/{$slug}/analyzing-bi/simulador-compra", 200, 403, 401],
    ['vendas e estornos',   'GET',  "/api/loja/{$slug}/orders/vendas",       200, 403, 401],
    ['clientes',            'GET',  "/api/loja/{$slug}/clientes",            200, 403, 401],
    ['avaliações (moderar)','GET',  "/api/loja/{$slug}/avaliacoes",          200, 403, 401],

    // Público:
    ['categorias públicas', 'GET',  "/api/loja/{$slug}/vitrine-categories/public", 200, 200, 200],

    // Removidas: nenhuma tela as usava, e entregavam receita e e-mails da equipe.
    ['ai/reports/last30',   'GET',  "/api/loja/{$slug}/ai/reports/last30",   404, 404, 404],
    ['ai/snapshot',         'GET',  "/api/loja/{$slug}/ai/snapshot",         404, 404, 404],
];

echo PHP_EOL . 'Leitura' . PHP_EOL;
foreach ($casos as [$rotulo, $metodo, $caminho, $espGer, $espFun, $espAnon]) {
    $g = http($metodo, $base . $caminho, $jarGerente)['status'];
    $f = http($metodo, $base . $caminho, $jarFunc)['status'];
    $a = http($metodo, $base . $caminho, null)['status'];
    $ok(sprintf('%-22s gerente=%d funcionário=%d anônimo=%d', $rotulo, $g, $f, $a),
        $g === $espGer && $f === $espFun && $a === $espAnon);
}

// -------------------------------------- dados financeiros na resposta
// Status 200 não basta aqui: a rota é aberta ao funcionário, o que importa é o
// que vem dentro dela.
echo PHP_EOL . 'Dados financeiros fora do alcance do funcionário' . PHP_EOL;

$jsonDe = static fn (string $caminho, string $jar): array => json_decode(http('GET', $base . $caminho, $jar)['body'], true) ?: [];
$temCusto = static fn (array $lista): bool => $lista !== [] && array_key_exists('cost_price', $lista[0]);

$prodGer = $jsonDe("/api/loja/{$slug}/products", $jarGerente)['products'] ?? [];
$prodFun = $jsonDe("/api/loja/{$slug}/products", $jarFunc)['products'] ?? [];
$ok('gerente recebe o custo em GET /products', $temCusto($prodGer));
$ok('funcionário não recebe o custo em GET /products', $prodFun !== [] && !$temCusto($prodFun));

$idProduto = (int) ($prodFun[0]['id'] ?? 0);
$um = $jsonDe("/api/loja/{$slug}/products/{$idProduto}", $jarFunc);
$ok('funcionário não recebe o custo em GET /products/{id}', isset($um['id']) && !array_key_exists('cost_price', $um));

$baixo = $jsonDe("/api/loja/{$slug}/products/low-stock", $jarFunc)['products'] ?? [];
$ok('funcionário não recebe o custo em GET /products/low-stock', $baixo !== [] && !$temCusto($baixo));

$metasGer = $jsonDe("/api/loja/{$slug}/goals", $jarGerente);
$metasFun = $jsonDe("/api/loja/{$slug}/goals", $jarFunc);
$ok('gerente vê a meta e o faturamento da loja', array_key_exists('store_goal', $metasGer) && array_key_exists('store_sales', $metasGer));
$ok('funcionário não vê a meta nem o faturamento da loja', !array_key_exists('store_goal', $metasFun) && !array_key_exists('store_sales', $metasFun));
$ok('funcionário vê só a própria linha de metas', count($metasFun['employees'] ?? []) === 1);
// O rateio é de um valor informado: a meta da loja inclui vendas sem funcionário.
$rateio = static fn (array $corpo): int => http('POST', "{$base}/api/loja/{$slug}/goals/store", $jarGerente, [
    'json' => $corpo + ['period' => date('Y-m'), 'goal_amount' => 1000, 'distribute_to_employees' => true],
    'csrf' => $csrfGerente,
])['status'];
$ok('dividir sem informar o valor é recusado', $rateio([]) === 400);
$ok('dividir mais que a meta da loja é recusado', $rateio(['distribute_amount' => 1000.01]) === 400);
$idGerente = (int) ((function () use ($emailGerente) { $q = \App\Database\Database::getConnection()->prepare('SELECT id FROM users WHERE email = ?'); $q->execute([$emailGerente]); return $q->fetchColumn(); })());
$ok('o gerente não entra na lista de metas da equipe', !in_array($idGerente, array_column($metasGer['employees'] ?? [], 'user_id'), true));

// Item de pedido guarda o custo congelado da venda. Cria uma venda de balcão,
// confere a resposta e cancela, para não deixar reserva de estoque para trás.
$semVariacao = null;
foreach ($prodFun as $p) {
    if (empty($p['variants']) && (int) $p['stock_quantity'] > 0) {
        $semVariacao = (int) $p['id'];
        break;
    }
}
// Tudo o que este trecho muda no banco é desfeito por $desfazer: no finally
// e, se o script morrer no meio (exit, erro fatal), no shutdown.
// O @var int evita que o PHPStan fixe o tipo em `0` dentro da closure: ela
// captura as variáveis por referência, e os ids só são atribuídos depois.
/** @var int $idVenda */
$idVenda = 0;
/** @var int $idCompra */
$idCompra = 0;
$desfeito = false;
$desfazer = static function () use (&$idVenda, &$idCompra, &$desfeito, $base, $slug, $jarGerente, $csrfGerente): void {
    if ($desfeito) {
        return;
    }
    $desfeito = true;
    $ids = array_filter([$idVenda, $idCompra]);
    if ($ids === []) {
        return;
    }
    $pdo = \App\Database\Database::getConnection();
    // Volta ao estado em que a API criou os pedidos: pendentes, e a compra
    // da vitrine sem vendedor. Só então o cancelamento devolve a reserva.
    $pdo->prepare("UPDATE orders SET status = 'pendente' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND status = 'pago'")->execute();
    if ($idCompra > 0) {
        $pdo->prepare('UPDATE orders SET created_by = NULL WHERE id = ?')->execute([$idCompra]);
    }
    foreach ($ids as $id) {
        http('POST', "{$base}/api/loja/{$slug}/orders/{$id}/cancel", $jarGerente, ['json' => [], 'csrf' => $csrfGerente]);
    }
};
register_shutdown_function($desfazer);

try {
$venda = json_decode(http('POST', "{$base}/api/loja/{$slug}/orders", $jarFunc, [
    'json' => ['order_type' => 'pdv', 'customer_name' => '__authz_teste__', 'items' => [['product_id' => $semVariacao, 'quantity' => 1]]],
    'csrf' => $csrfFunc,
])['body'], true) ?: [];
$idVenda = (int) ($venda['order']['id'] ?? 0);
$pedido = $jsonDe("/api/loja/{$slug}/orders/{$idVenda}", $jarFunc);
$ok('item de pedido não traz o custo', !empty($pedido['items']) && !array_key_exists('cost_price', $pedido['items'][0]));

// Só o PDV credita a venda: na vitrine, quem é da equipe está comprando para si.
$pdo = \App\Database\Database::getConnection();
$operador = static function (int $id) use ($pdo) {
    $q = $pdo->prepare('SELECT created_by FROM orders WHERE id = ?');
    $q->execute([$id]);
    return $q->fetchColumn();
};
$pagar = static fn (int $id, string $status) => $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
$vendidoFunc = static fn (): float => (float) ($jsonDe("/api/loja/{$slug}/goals", $jarFunc)['employees'][0]['total_sales'] ?? 0);
$idFunc = (int) ($metasFun['employees'][0]['user_id'] ?? 0);

$compra = json_decode(http('POST', "{$base}/api/loja/{$slug}/orders", $jarFunc, [
    'json' => ['order_type' => 'online', 'items' => [['product_id' => $semVariacao, 'quantity' => 1]]],
    'csrf' => $csrfFunc,
])['body'], true) ?: [];
$idCompra = (int) ($compra['order']['id'] ?? 0);
$ok('venda no PDV fica no nome de quem registrou', $idVenda > 0 && (int) $operador($idVenda) === $idFunc);
$ok('compra na vitrine não fica no nome do funcionário', $idCompra > 0 && $operador($idCompra) === null);

// O status vai a pago só durante a leitura; a limpeza abaixo devolve a reserva.
$antes = $vendidoFunc();
$pagar($idVenda, 'pago');
$comVenda = $vendidoFunc();
$ok('venda paga no PDV conta para a meta', $comVenda > $antes);
$pagar($idVenda, 'pendente');
// Pedido online antigo, de quando a compra era creditada.
$pdo->prepare('UPDATE orders SET created_by = ? WHERE id = ?')->execute([$idFunc, $idCompra]);
$pagar($idCompra, 'pago');
$ok('compra antiga na vitrine não conta para a meta', abs($vendidoFunc() - $antes) < 0.01);
} finally {
    $desfazer();
}

// ---------------------------------------------- escrita: o furo da Fase 2
echo PHP_EOL . 'Escrita (o funcionário podia tudo pela API)' . PHP_EOL;

$escritas = [
    ['criar produto', 'POST', "/api/loja/{$slug}/products",
        ['name' => '__authz_teste__', 'sale_price' => 10, 'stock_quantity' => 1]],
    ['ajustar estoque', 'POST', "/api/loja/{$slug}/products/1/stock",
        ['quantity' => 1, 'type' => 'entrada']],
    ['criar categoria', 'POST', "/api/loja/{$slug}/vitrine-categories",
        ['name' => '__authz_teste__', 'icon_key' => 'treino']],
    ['criar funcionário', 'POST', "/api/loja/{$slug}/users",
        ['name' => 'X', 'email' => '__authz__@x.test', 'password' => 'abc12345', 'user_type' => 'funcionario']],
    ['mudar nome da loja', 'POST', "/api/loja/{$slug}/store/name", ['name' => 'Nome Novo']],
    ['definir meta', 'POST', "/api/loja/{$slug}/goals/store",
        ['period' => date('Y-m'), 'goal_amount' => 1000]],
    ['estornar venda', 'POST', "/api/loja/{$slug}/orders/1/estornar",
        ['reason' => 'teste', 'return_to_stock' => true]],
    ['definir meta individual', 'POST', "/api/loja/{$slug}/goals/employee",
        ['period' => date('Y-m'), 'user_id' => 1, 'goal_amount' => 1000]],
    ['criar cargo', 'POST', "/api/loja/{$slug}/roles", ['name' => '__authz_teste__']],
    ['remover avaliação', 'POST', "/api/loja/{$slug}/avaliacoes/1/remover", []],
];

foreach ($escritas as [$rotulo, $metodo, $caminho, $corpo]) {
    $f = http($metodo, $base . $caminho, $jarFunc, ['json' => $corpo, 'csrf' => $csrfFunc])['status'];
    $a = http($metodo, $base . $caminho, null, ['json' => $corpo])['status'];
    $ok(sprintf('%-22s funcionário=%d (403) anônimo=%d (401/403)', $rotulo, $f, $a),
        $f === 403 && in_array($a, [401, 403], true));
}

// O gerente continua conseguindo — a regra não pode ter travado a loja.
echo PHP_EOL . 'O gerente continua trabalhando' . PHP_EOL;
$r = http('POST', "{$base}/api/loja/{$slug}/vitrine-categories", $jarGerente,
    ['json' => ['name' => '__authz_teste__', 'icon_key' => 'treino'], 'csrf' => $csrfGerente]);
$ok('gerente cria categoria (200)', $r['status'] === 200);
$catId = json_decode($r['body'], true)['category']['id'] ?? null;
if ($catId) {
    $d = http('DELETE', "{$base}/api/loja/{$slug}/vitrine-categories/{$catId}", $jarGerente, ['csrf' => $csrfGerente]);
    $ok('gerente apaga a categoria (200)', $d['status'] === 200);
}

// ------------------------------------------------------- páginas do painel
echo PHP_EOL . 'Páginas do painel' . PHP_EOL;
$paginas = [
    ['dashboard',    "/painel/{$slug}",                200, 200],
    ['produtos',     "/painel/{$slug}/produtos",       200, 200],
    ['PDV',          "/painel/{$slug}/pdv",            200, 200],
    ['metas',        "/painel/{$slug}/metas",          200, 200],
    ['vendas',       "/painel/{$slug}/vendas",         200, 302],
    ['clientes',     "/painel/{$slug}/clientes",       200, 302],
    ['avaliações',   "/painel/{$slug}/avaliacoes",     200, 302],
    ['funcionários', "/painel/{$slug}/funcionarios",   200, 302],
    ['configurações', "/painel/{$slug}/configuracoes", 200, 302],
    ['Analyzing BI', "/painel/{$slug}/analyzing-bi",   200, 302],
];
foreach ($paginas as [$rotulo, $caminho, $espGer, $espFun]) {
    $g = http('GET', $base . $caminho, $jarGerente)['status'];
    $f = http('GET', $base . $caminho, $jarFunc)['status'];
    $ok(sprintf('%-14s gerente=%d funcionário=%d', $rotulo, $g, $f), $g === $espGer && $f === $espFun);
}

@unlink($jarGerente);
@unlink($jarFunc);

echo PHP_EOL . $total . ' verificações — ' . ($falhas === 0 ? 'TUDO PASSOU' : $falhas . ' FALHARAM') . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
