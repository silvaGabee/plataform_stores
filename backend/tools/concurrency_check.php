<?php
/**
 * Verifica, contra o banco real, que a baixa de estoque não vende a descoberto
 * e que a confirmação de pagamento não roda duas vezes.
 *
 * Uso: php backend/tools/concurrency_check.php
 *
 * Cria os próprios dados (loja, produto, cliente, pedidos) num prefixo
 * reconhecível e apaga tudo ao final, inclusive se falhar no meio.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Database\Database;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashRegisterRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;
use App\Repositories\StockMovementRepository;
use App\Services\OrderService;

$pdo = Database::getConnection();
$falhas = 0;
$ok = static function (string $nome, bool $passou) use (&$falhas): void {
    if (!$passou) {
        $falhas++;
    }
    echo ($passou ? '  PASSA  ' : '  FALHA  ') . $nome . PHP_EOL;
};

// ---------------------------------------------------------------- preparação
$pdo->exec("DELETE FROM stores WHERE slug LIKE '__conc_test__%'");
$pdo->exec("DELETE FROM users WHERE email LIKE '__conc_test__%'");

$pdo->prepare('INSERT INTO stores (name, slug) VALUES (?, ?)')
    ->execute(['Loja Teste Concorrência', '__conc_test__loja']);
$storeId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO users (store_id, name, email, password, user_type) VALUES (?, ?, ?, ?, ?)')
    ->execute([$storeId, 'Cliente Teste', '__conc_test__@local.test', password_hash('x', PASSWORD_DEFAULT), 'cliente']);
$customerId = (int) $pdo->lastInsertId();

$orderService = static fn (): OrderService => new OrderService(
    new OrderRepository(),
    new OrderItemRepository(),
    new ProductRepository(),
    new PaymentRepository(),
    new StockMovementRepository(),
    new CashRegisterRepository(),
    new CashMovementRepository()
);

$novoProduto = static function (int $storeId, int $estoque) use ($pdo): int {
    $pdo->prepare('INSERT INTO products (store_id, name, sale_price, cost_price, stock_quantity) VALUES (?, ?, ?, ?, ?)')
        ->execute([$storeId, 'Produto Teste', 100.00, 50.00, $estoque]);

    return (int) $pdo->lastInsertId();
};

$estoqueDe = static function (int $productId) use ($pdo): int {
    $s = $pdo->prepare('SELECT stock_quantity FROM products WHERE id = ?');
    $s->execute([$productId]);

    return (int) $s->fetchColumn();
};

try {
    echo PHP_EOL . 'Baixa de estoque' . PHP_EOL;

    // 1) A última unidade só pode ser vendida uma vez.
    $p = $novoProduto($storeId, 1);
    $repo = new ProductRepository();
    $primeira = $repo->decrementStock($p, 1);
    $segunda  = $repo->decrementStock($p, 1);
    $ok('a primeira baixa da última unidade passa', $primeira === true);
    $ok('a segunda baixa é RECUSADA (antes cortava em zero)', $segunda === false);
    $ok('estoque final é 0, nunca negativo', $estoqueDe($p) === 0);

    // 2) Pedir mais do que existe é recusado por inteiro, sem baixa parcial.
    $p2 = $novoProduto($storeId, 3);
    $ok('baixa de 5 sobre estoque 3 é recusada', $repo->decrementStock($p2, 5) === false);
    $ok('estoque permanece 3 (sem baixa parcial)', $estoqueDe($p2) === 3);

    echo PHP_EOL . 'Transação' . PHP_EOL;

    // 3) Exceção dentro da transação desfaz o que já tinha sido escrito.
    $p3 = $novoProduto($storeId, 10);
    try {
        Database::transaction(static function () use ($repo, $p3): void {
            $repo->decrementStock($p3, 4);
            throw new RuntimeException('falha simulada no meio da transação');
        });
    } catch (RuntimeException $e) {
        // esperado
    }
    $ok('rollback devolve o estoque para 10', $estoqueDe($p3) === 10);

    // 4) Transação aninhada não estoura e desfaz o conjunto todo.
    $p4 = $novoProduto($storeId, 10);
    try {
        Database::transaction(static function () use ($repo, $p4): void {
            $repo->decrementStock($p4, 1);
            Database::transaction(static function () use ($repo, $p4): void {
                $repo->decrementStock($p4, 1);
                throw new RuntimeException('falha na transação interna');
            });
        });
    } catch (RuntimeException $e) {
        // esperado
    }
    $ok('aninhamento não lança e o rollback é total (10)', $estoqueDe($p4) === 10);

    echo PHP_EOL . 'Confirmação de pagamento' . PHP_EOL;

    // 5) Confirmar duas vezes baixa o estoque uma vez só.
    $p5 = $novoProduto($storeId, 5);
    $svc = $orderService();
    $pedido = $svc->createOrder($storeId, $customerId, [['product_id' => $p5, 'quantity' => 2]], 'online');
    $pag = $svc->addPayment((int) $pedido['id'], $storeId, 'pix', (float) $pedido['total']);
    $svc->confirmPayment((int) $pag['id'], $storeId);
    $depoisDaPrimeira = $estoqueDe($p5);

    $recusou = false;
    try {
        $svc->confirmPayment((int) $pag['id'], $storeId);
    } catch (Throwable $e) {
        $recusou = true;
    }
    $ok('primeira confirmação baixa 2 (5 -> 3)', $depoisDaPrimeira === 3);
    $ok('segunda confirmação é recusada', $recusou);
    $ok('estoque continua 3, não 1', $estoqueDe($p5) === 3);

    // 6) Estoque some entre o pedido e a confirmação: nada é gravado.
    $p6 = $novoProduto($storeId, 5);
    $pedido6 = $svc->createOrder($storeId, $customerId, [['product_id' => $p6, 'quantity' => 4]], 'online');
    $pag6 = $svc->addPayment((int) $pedido6['id'], $storeId, 'pix', (float) $pedido6['total']);
    $pdo->prepare('UPDATE products SET stock_quantity = 1 WHERE id = ?')->execute([$p6]);

    $recusou6 = false;
    try {
        $svc->confirmPayment((int) $pag6['id'], $storeId);
    } catch (Throwable $e) {
        $recusou6 = true;
    }
    $st = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $st->execute([(int) $pedido6['id']]);
    $statusPedido = (string) $st->fetchColumn();
    $st2 = $pdo->prepare('SELECT status FROM payments WHERE id = ?');
    $st2->execute([(int) $pag6['id']]);
    $statusPag = (string) $st2->fetchColumn();
    $st3 = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?');
    $st3->execute([$p6]);
    $movimentos = (int) $st3->fetchColumn();

    $ok('confirmação sem estoque é recusada', $recusou6);
    $ok('pedido continua "pendente" (não virou pago)', $statusPedido === 'pendente');
    $ok('pagamento continua "pendente"', $statusPag === 'pendente');
    $ok('nenhum movimento de estoque foi gravado', $movimentos === 0);
    $ok('estoque intacto em 1', $estoqueDe($p6) === 1);

    // 7) Duplo clique no pagamento não cria dois registros.
    $p7 = $novoProduto($storeId, 5);
    $pedido7 = $svc->createOrder($storeId, $customerId, [['product_id' => $p7, 'quantity' => 1]], 'online');
    $a = $svc->addPayment((int) $pedido7['id'], $storeId, 'pix', (float) $pedido7['total']);
    $b = $svc->addPayment((int) $pedido7['id'], $storeId, 'pix', (float) $pedido7['total']);
    $ok('segundo pedido de pagamento idêntico devolve o mesmo registro', (int) $a['id'] === (int) $b['id']);

    // 8) Trocar de método continua funcionando.
    $c = $svc->addPayment((int) $pedido7['id'], $storeId, 'cartao', (float) $pedido7['total']);
    $ok('trocar PIX por cartão cria um pagamento novo', (int) $c['id'] !== (int) $a['id']);
    $st4 = $pdo->prepare("SELECT status FROM payments WHERE id = ?");
    $st4->execute([(int) $a['id']]);
    $ok('o pagamento PIX anterior fica "cancelado"', (string) $st4->fetchColumn() === 'cancelado');
} finally {
    // ------------------------------------------------------------- limpeza
    $pdo->exec("DELETE FROM stores WHERE slug LIKE '__conc_test__%'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '__conc_test__%'");
    echo PHP_EOL . 'dados de teste removidos' . PHP_EOL;
}

echo PHP_EOL . ($falhas === 0 ? 'TUDO PASSOU' : $falhas . ' VERIFICAÇÃO(ÕES) FALHARAM') . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
