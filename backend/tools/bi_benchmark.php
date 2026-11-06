<?php
/**
 * Benchmark e conferência do Analyzing BI em grande volume.
 *
 *   php backend/tools/bi_benchmark.php [--pedidos=50000] [--manter]
 *
 * Cria um banco SEPARADO (plataform_stores_bench) a partir do schema.sql,
 * gera uma loja sintética com dados determinísticos (semente fixa) e:
 *
 *  1. CONFERE os números do BI contra um cálculo independente feito aqui em
 *     PHP, item a item, sobre os mesmos dados gerados — faturamento, número de
 *     pedidos, lucro com custo congelado e desconto rateado;
 *  2. prova que o custo está congelado: depois de gerar as vendas, todos os
 *     custos do cadastro são alterados, e o lucro tem de continuar igual;
 *  3. MEDE o tempo de cada consulta (mediana de 5 execuções).
 *
 * O banco real nunca é tocado. Sem --manter, o banco de benchmark é apagado
 * no fim.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Repositories\AnalyzingBIRepository;
use App\Services\AnalyzingBIService;

$opts = getopt('', ['pedidos::', 'manter']);
$nPedidos = max(100, (int) ($opts['pedidos'] ?? 50000));
$manter = isset($opts['manter']);

$cfg = require PLATAFORM_BACKEND . '/config/database.php';
$bench = 'plataform_stores_bench';
if ($cfg['dbname'] === $bench) {
    fwrite(STDERR, "O banco configurado já é o de benchmark; recusando.\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$cfg['host']};charset={$cfg['charset']}", $cfg['username'], $cfg['password'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$t0 = microtime(true);
echo "Banco de benchmark: {$bench} (o banco real '{$cfg['dbname']}' não é tocado)\n";

// ------------------------------------------------------------------ schema
$pdo->exec("DROP DATABASE IF EXISTS `{$bench}`");
$sql = file_get_contents(PLATAFORM_BACKEND . '/database/schema.sql');
$sql = preg_replace('/CREATE DATABASE IF NOT EXISTS plataform_stores\b/', "CREATE DATABASE IF NOT EXISTS `{$bench}`", $sql);
$sql = preg_replace('/^USE plataform_stores;/m', "USE `{$bench}`;", $sql);
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql)))) as $stmt) {
    $pdo->exec($stmt);
}
$pdo->exec("USE `{$bench}`");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// ------------------------------------------------------------------ dados
mt_srand(42);
$tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
$agora = new DateTimeImmutable('now', $tz);
$inicioJanela = $agora->modify('-365 days')->getTimestamp();
$fimJanela = $agora->getTimestamp();

$pdo->exec("INSERT INTO users (id, name, email, password) VALUES (1, 'Bench', 'bench@local', 'x')");
$pdo->exec("INSERT INTO stores (id, name, slug) VALUES (1, 'Loja Benchmark', 'bench')");

$nProdutos = 300;
$produtos = [];
$linhas = [];
for ($p = 1; $p <= $nProdutos; $p++) {
    $custo = mt_rand(1000, 20000) / 100;
    $preco = round($custo * (mt_rand(130, 250) / 100), 2);
    $produtos[$p] = ['custo' => $custo, 'preco' => $preco];
    $linhas[] = sprintf("(%d, 1, 'Produto %d', %.2f, %.2f, 1000, 5)", $p, $p, $custo, $preco);
}
$pdo->exec('INSERT INTO products (id, store_id, name, cost_price, sale_price, stock_quantity, min_stock) VALUES ' . implode(',', $linhas));

$esperado = ['faturamento' => 0, 'pedidos' => 0, 'lucro_cent' => 0, 'cmv_cent' => 0, 'frete_cent' => 0];
$lote = 2000;
$itemId = 0;
$pedidosSql = [];
$itensSql = [];
// Os lotes entram como parâmetros por referência, e não pelo `use (&...)`: o
// PHPStan não acompanha o que o laço põe num array capturado assim, acha que ele
// continua vazio e acusava os `if` abaixo como sempre falsos.
$flush = static function (array &$pedidosSql, array &$itensSql) use ($pdo): void {
    if ($pedidosSql !== []) {
        $pdo->exec('INSERT INTO orders (id, store_id, customer_id, order_type, status, total, discount, shipping_fee, created_at) VALUES' . implode(',', $pedidosSql));
    }
    if ($itensSql !== []) {
        $pdo->exec('INSERT INTO order_items (id, order_id, product_id, quantity, price, cost_price) VALUES ' . implode(',', $itensSql));
    }
    $pedidosSql = [];
    $itensSql = [];
};

for ($o = 1; $o <= $nPedidos; $o++) {
    $r = mt_rand(1, 100);
    $status = $r <= 85 ? 'pago' : ($r <= 90 ? 'enviado' : ($r <= 96 ? 'pendente' : 'cancelado'));
    $tipo = mt_rand(0, 1) ? 'pdv' : 'online';
    $quando = date('Y-m-d H:i:s', mt_rand($inicioJanela, $fimJanela));

    $subtotalCent = 0;
    $margemCent = 0;
    $cmvCent = 0;
    $nItens = mt_rand(1, 4);
    $usados = [];
    for ($k = 0; $k < $nItens; $k++) {
        $pid = mt_rand(1, $nProdutos);
        if (isset($usados[$pid])) {
            continue;
        }
        $usados[$pid] = true;
        $q = mt_rand(1, 3);
        $preco = $produtos[$pid]['preco'];
        $custo = $produtos[$pid]['custo'];
        $subtotalCent += (int) round($q * $preco * 100);
        $margemCent += (int) round($q * ($preco - $custo) * 100);
        $cmvCent += (int) round($q * $custo * 100);
        $itensSql[] = sprintf('(%d, %d, %d, %d, %.2f, %.2f)', ++$itemId, $o, $pid, $q, $preco, $custo);
    }
    // ~20% dos pedidos de balcão com desconto de até 15% do subtotal.
    $descontoCent = ($tipo === 'pdv' && mt_rand(1, 5) === 1) ? (int) floor($subtotalCent * mt_rand(1, 15) / 100) : 0;
    // Pedido online paga frete, que entra em orders.total, mas não na receita.
    // Vem do número do pedido, e não de mt_rand(), para não mudar a sequência
    // sorteada: os demais dados continuam iguais aos das medições anteriores.
    $freteCent = $tipo === 'online' ? 500 + ($o % 30) * 100 : 0;
    $receitaCent = $subtotalCent - $descontoCent;
    $pedidosSql[] = sprintf("(%d, 1, 1, '%s', '%s', %.2f, %.2f, %.2f, '%s')", $o, $tipo, $status, ($receitaCent + $freteCent) / 100, $descontoCent / 100, $freteCent / 100, $quando);

    if ($status === 'pago') {
        $esperado['faturamento'] += $receitaCent;
        $esperado['pedidos']++;
        $esperado['lucro_cent'] += $margemCent - $descontoCent;
        $esperado['cmv_cent'] += $cmvCent;
        $esperado['frete_cent'] += $freteCent;
    }
    if ($o % $lote === 0) {
        $flush($pedidosSql, $itensSql);
    }
}
$flush($pedidosSql, $itensSql);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
// Depois de uma carga em massa as estatísticas do InnoDB estão velhas e o
// otimizador escolhe planos ruins (medido: 26 s contra 1,5 s na mesma
// consulta). Um banco em produção tem estatísticas atualizadas; medir sem
// isto mediria um cenário que não existe.
foreach (['orders', 'order_items', 'products'] as $tabela) {
    $pdo->query("ANALYZE TABLE {$tabela}")->fetchAll();
}
printf("Dados gerados: %d pedidos, %d itens, %d produtos em %.1fs\n\n", $nPedidos, $itemId, $nProdutos, microtime(true) - $t0);

// ------------------------------------------------------------------ conferência
$repo = new AnalyzingBIRepository($pdo);
$service = new AnalyzingBIService($repo);
$falhas = 0;
$confere = static function (string $nome, float $esperadoV, float $obtido) use (&$falhas): void {
    $ok = abs($esperadoV - $obtido) < 0.05;
    if (!$ok) {
        $falhas++;
    }
    printf("  %s  %-48s esperado %14s  obtido %14s\n", $ok ? 'PASSA' : 'FALHA', $nome, number_format($esperadoV, 2, ',', '.'), number_format($obtido, 2, ',', '.'));
};

echo "Conferência (BI × cálculo independente em PHP)\n";
$faturamento = $repo->sumPaidOrdersTotal(1, null, null);
printf("  Frete dos pedidos pagos, fora do faturamento: R$ %s\n", number_format($esperado['frete_cent'] / 100, 2, ',', '.'));
$confere('Faturamento (pedidos pagos, sem frete)', $esperado['faturamento'] / 100, $faturamento);
$faturamentoTotal = (float) $pdo->query("SELECT SUM(total) FROM orders WHERE status = 'pago'")->fetchColumn();
$confere('Σ orders.total − frete = faturamento', $faturamentoTotal - $esperado['frete_cent'] / 100, $faturamento);
$confere('Quantidade de pedidos pagos', $esperado['pedidos'], $repo->countPaidOrders(1, null, null));
$lucroAntes = $repo->sumEstimatedProfit(1, null, null);
// O rateio do desconto é proporcional e feito em ponto flutuante pelo banco;
// a soma das partes reconstitui o desconto com erro de frações de centavo.
$confere('Lucro (custo congelado, desconto rateado)', $esperado['lucro_cent'] / 100, $lucroAntes);
$confere('L = R − CMV', $faturamento - $esperado['cmv_cent'] / 100, $lucroAntes);
$somaPorProduto = array_sum(array_map(static fn ($r) => (float) $r['lucro_total'], $repo->fetchProductProfitByPeriod(1, null, null)));
$confere('Σ lucro por produto = lucro total', $lucroAntes, $somaPorProduto);
// Lucro do mês: tem de fechar com o lucro por produto do mesmo intervalo.
$mesIni = $agora->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
$mesFim = $agora->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
$somaMes = array_sum(array_map(static fn ($r) => (float) $r['lucro_total'], $repo->fetchProductProfitByPeriod(1, $mesIni, $mesFim)));
$confere('Lucro do mês = Σ lucro por produto do mês', $repo->sumEstimatedProfit(1, $mesIni, $mesFim), $somaMes);
// Curva ABC: a receita por produto, com o desconto rateado, tem de fechar com o faturamento.
$anoIni = $agora->modify('-12 months')->format('Y-m-d H:i:s');
$somaAbc = array_sum(array_column($service->buildIndicators(1)['abc']['itens'], 'receita'));
$confere('Σ receita da Curva ABC = faturamento em 12 meses', $repo->sumPaidOrdersTotal(1, $anoIni, null), $somaAbc);

// Muda TODOS os custos do cadastro. O lucro das vendas já feitas não pode mudar.
$pdo->exec('UPDATE products SET cost_price = cost_price * 1.5');
$confere('Lucro após custo do cadastro subir 50%', $lucroAntes, $repo->sumEstimatedProfit(1, null, null));

// ------------------------------------------------------------------ tempo
echo "\nTempo (mediana de 5 execuções)\n";
$medir = static function (string $nome, callable $fn): void {
    $t = [];
    for ($i = 0; $i < 5; $i++) {
        $s = hrtime(true);
        $fn();
        $t[] = (hrtime(true) - $s) / 1e6;
    }
    sort($t);
    printf("  %-48s %8.1f ms\n", $nome, $t[2]);
};
$medir('buildPayload (painel inteiro)', fn () => $service->buildPayload(1));
$medir('Indicadores (capital, ABC, giro, cupons)', fn () => $service->buildIndicators(1));
$medir('Faturamento 7 dias + tendência', fn () => $service->getFaturamentoAoLongoDoTempo(1, '7d'));
$medir('Faturamento 30 dias + tendência', fn () => $service->getFaturamentoAoLongoDoTempo(1, '30d'));
$medir('Faturamento 3 meses + tendência', fn () => $service->getFaturamentoAoLongoDoTempo(1, '3m'));
$medir('sumEstimatedProfit (todo o período)', fn () => $repo->sumEstimatedProfit(1, null, null));
$medir('fetchProductProfitByPeriod (todo o período)', fn () => $repo->fetchProductProfitByPeriod(1, null, null));

$f = $service->getFaturamentoAoLongoDoTempo(1, '30d');
$prev = $f['meta']['previsao'];
printf("\nTendência 30d: %d pontos projetados, inclinação %s/dia, R² %s\n",
    count($f['serie_previsao'] ?? []) - 1,
    $prev['inclinacao_por_periodo'] ?? '-',
    $prev['r2'] ?? '-'
);

echo "\nPlano de execução (EXPLAIN) do lucro:\n";
$plano = $pdo->query("EXPLAIN SELECT SUM(oi.quantity * (oi.price - COALESCE(oi.cost_price, 0)))
    FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id AND o.store_id = 1 AND o.status = 'pago'
    WHERE o.created_at >= '2000-01-01'")->fetchAll();
foreach ($plano as $l) {
    printf("  %-3s %-6s key=%-22s rows=%-8s %s\n", $l['table'], $l['type'], $l['key'] ?? 'NULL', $l['rows'], $l['Extra'] ?? '');
}

if (!$manter) {
    $pdo->exec("DROP DATABASE `{$bench}`");
    echo "\nBanco de benchmark removido.\n";
}
printf("Total: %.1fs — %s\n", microtime(true) - $t0, $falhas === 0 ? 'CONFERÊNCIA OK' : "{$falhas} FALHA(S)");
exit($falhas === 0 ? 0 : 1);
