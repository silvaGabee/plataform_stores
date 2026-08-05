<?php
/**
 * Exercita a matriz de permissões e o CSRF por HTTP, como um cliente real.
 *
 *   php backend/tools/authz_check.php [url-base]
 *
 * Faz login de verdade (formulário + token), guarda o cookie de sessão e chama
 * cada endpoint como gerente, como funcionário e sem sessão, comparando o
 * status HTTP com o esperado.
 *
 * Depende do seed: php backend/scripts/seed.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

$base = rtrim($argv[1] ?? (getenv('APP_URL') ?: 'http://localhost/plataform_stores/public'), '/');
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

/** Faz login pelo formulário, com token — como o navegador faria. */
function login(string $base, string $email, string $senha, string $jar): ?string
{
    @unlink($jar);
    $pagina = http('GET', $base . '/', $jar);
    if (!preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $pagina['body'], $m)) {
        return null;
    }
    $token = $m[1];
    $r = http('POST', $base . '/login', $jar, [
        'form' => ['auth_intent' => 'login', 'email' => $email, 'password' => $senha, '_csrf' => $token],
    ]);
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

$csrfGerente = login($base, 'gerente@loja.test', 'gerente123', $jarGerente);
$csrfFunc = login($base, 'funcionario@loja.test', 'gerente123', $jarFunc);

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
    ['movimentos estoque',  'GET',  "/api/loja/{$slug}/stock-movements",     200, 200, 401],
    ['ver pedidos',         'GET',  "/api/loja/{$slug}/orders",              200, 200, 401],
    ['ver entregas',        'GET',  "/api/loja/{$slug}/orders/entregas",     200, 200, 401],
    ['caixa',               'GET',  "/api/loja/{$slug}/cash/status",         200, 200, 401],
    ['relatório de vendas', 'GET',  "/api/loja/{$slug}/reports/sales",       200, 200, 401],
    ['ver metas',           'GET',  "/api/loja/{$slug}/goals",               200, 200, 401],
    ['ver cargos',          'GET',  "/api/loja/{$slug}/roles",               200, 200, 401],
    ['config da vitrine',   'GET',  "/api/loja/{$slug}/dashboard-config",    200, 403, 401],

    // Só gerente:
    ['Analyzing BI',        'GET',  "/api/loja/{$slug}/analyzing-bi",        200, 403, 401],
    ['listar equipe',       'GET',  "/api/loja/{$slug}/users",               200, 403, 401],
    ['config PIX',          'GET',  "/api/loja/{$slug}/pix-config",          200, 403, 401],
    ['pagamentos pendentes','GET',  "/api/loja/{$slug}/payments/pending",    200, 403, 401],

    // Público:
    ['categorias públicas', 'GET',  "/api/loja/{$slug}/vitrine-categories/public", 200, 200, 200],
];

echo PHP_EOL . 'Leitura' . PHP_EOL;
foreach ($casos as [$rotulo, $metodo, $caminho, $espGer, $espFun, $espAnon]) {
    $g = http($metodo, $base . $caminho, $jarGerente)['status'];
    $f = http($metodo, $base . $caminho, $jarFunc)['status'];
    $a = http($metodo, $base . $caminho, null)['status'];
    $ok(sprintf('%-22s gerente=%d funcionário=%d anônimo=%d', $rotulo, $g, $f, $a),
        $g === $espGer && $f === $espFun && $a === $espAnon);
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
    ['criar cargo', 'POST', "/api/loja/{$slug}/roles", ['name' => '__authz_teste__']],
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
