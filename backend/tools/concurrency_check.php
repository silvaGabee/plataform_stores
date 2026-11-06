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

// Cliente é quem não tem vínculo com loja nenhuma — users não guarda mais
// store_id nem user_type.
$pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)')
    ->execute(['Cliente Teste', '__conc_test__@local.test', password_hash('x', PASSWORD_DEFAULT)]);
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
    } catch (Throwable $e) {
        // esperado: a transação relança depois de desfazer
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
    } catch (Throwable $e) {
        // esperado: a transação relança depois de desfazer
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

    echo PHP_EOL . 'Estorno' . PHP_EOL;

    $statusDe = static function (string $tabela, int $id) use ($pdo): string {
        $s = $pdo->prepare("SELECT status FROM {$tabela} WHERE id = ?");
        $s->execute([$id]);

        return (string) $s->fetchColumn();
    };
    $recusa = static function (callable $acao): bool {
        try {
            $acao();
        } catch (InvalidArgumentException $e) {
            return true;
        }

        return false;
    };

    // 9) Estornar uma vez devolve as peças, e a segunda tentativa é recusada.
    $p9 = $novoProduto($storeId, 5);
    $pedido9 = $svc->createOrder($storeId, $customerId, [['product_id' => $p9, 'quantity' => 2]], 'online');
    $pag9 = $svc->addPayment((int) $pedido9['id'], $storeId, 'pix', (float) $pedido9['total']);
    $svc->confirmPayment((int) $pag9['id'], $storeId);
    $ok('estorno de pedido pendente é recusado', $recusa(fn () => $svc->refundOrder((int) $pedido7['id'], $storeId, $customerId, 'x', true)));
    $ok('estorno sem motivo é recusado', $recusa(fn () => $svc->refundOrder((int) $pedido9['id'], $storeId, $customerId, '  ', true)));
    $receitaAntes = (new App\Repositories\AnalyzingBIRepository($pdo))->sumPaidOrdersTotal($storeId, null, null);
    $svc->refundOrder((int) $pedido9['id'], $storeId, $customerId, 'venda errada', true);
    $ok('o pedido vira "estornado"', $statusDe('orders', (int) $pedido9['id']) === 'estornado');
    $ok('o pagamento vira "estornado"', $statusDe('payments', (int) $pag9['id']) === 'estornado');
    $ok('as peças voltam (3 -> 5)', $estoqueDe($p9) === 5);
    $ok('segundo estorno é recusado', $recusa(fn () => $svc->refundOrder((int) $pedido9['id'], $storeId, $customerId, 'de novo', true)));
    $ok('o estoque não sobe duas vezes', $estoqueDe($p9) === 5);
    $st9 = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND type = 'devolucao'");
    $st9->execute([$p9]);
    $ok('fica uma movimentação de devolução', (int) $st9->fetchColumn() === 1);
    $ok('a venda estornada sai da receita do BI', abs($receitaAntes - (new App\Repositories\AnalyzingBIRepository($pdo))->sumPaidOrdersTotal($storeId, null, null) - (float) $pedido9['total']) < 0.01);

    // 10) Peça com defeito: o estorno sai, mas o estoque fica como está.
    $p10 = $novoProduto($storeId, 5);
    $pedido10 = $svc->createOrder($storeId, $customerId, [['product_id' => $p10, 'quantity' => 2]], 'online');
    $pag10 = $svc->addPayment((int) $pedido10['id'], $storeId, 'pix', (float) $pedido10['total']);
    $svc->confirmPayment((int) $pag10['id'], $storeId);
    $svc->refundOrder((int) $pedido10['id'], $storeId, $customerId, 'defeito', false);
    $ok('sem devolver ao estoque, o saldo continua 3', $estoqueDe($p10) === 3);

    // 11) Variação: repõe a combinação vendida e recalcula o total do produto.
    $p11 = $novoProduto($storeId, 8);
    (new ProductVariantRepository())->replaceForProduct($p11, product_variants_matrix_to_rows([
        'axis' => 'tamanho', 'colors' => ['Azul'], 'sizes' => ['P', 'M'], 'stock' => ['P' => ['Azul' => 5], 'M' => ['Azul' => 3]],
    ]));
    $pedido11 = $svc->createOrder($storeId, $customerId, [['product_id' => $p11, 'quantity' => 1, 'variant_key' => 'Azul|M']], 'online');
    $pag11 = $svc->addPayment((int) $pedido11['id'], $storeId, 'pix', (float) $pedido11['total']);
    $svc->confirmPayment((int) $pag11['id'], $storeId);
    $ok('a venda baixa a variação (total 7)', $estoqueDe($p11) === 7);
    $svc->refundOrder((int) $pedido11['id'], $storeId, $customerId, 'troca', true);
    $sv = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE product_id = ? AND variant_value = 'Azul|M'");
    $sv->execute([$p11]);
    $ok('o estorno repõe a variação vendida (M = 3)', (int) $sv->fetchColumn() === 3);
    $ok('o total do produto volta a 8', $estoqueDe($p11) === 8);

    // 12) Dinheiro no balcão: exige caixa aberto e lança a saída.
    $p12 = $novoProduto($storeId, 5);
    $caixaRepo = new CashRegisterRepository();
    $idCaixa = $caixaRepo->open(['store_id' => $storeId, 'opened_by' => $customerId, 'initial_amount' => 0]);
    $pedido12 = $svc->createOrder($storeId, $customerId, [['product_id' => $p12, 'quantity' => 1]], 'pdv', $customerId);
    $pag12 = $svc->addPayment((int) $pedido12['id'], $storeId, 'dinheiro', (float) $pedido12['total']);
    $svc->confirmPayment((int) $pag12['id'], $storeId);
    $pdo->prepare('UPDATE cash_registers SET closed_at = NOW() WHERE id = ?')->execute([$idCaixa]);
    $ok('estorno em dinheiro sem caixa aberto é recusado', $recusa(fn () => $svc->refundOrder((int) $pedido12['id'], $storeId, $customerId, 'x', true)));
    $ok('a recusa não altera o pedido', $statusDe('orders', (int) $pedido12['id']) === 'pago' && $estoqueDe($p12) === 4);
    $idCaixa2 = $caixaRepo->open(['store_id' => $storeId, 'opened_by' => $customerId, 'initial_amount' => 0]);
    $svc->refundOrder((int) $pedido12['id'], $storeId, $customerId, 'x', true);
    $sc = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE cash_register_id = ? AND type = 'saida'");
    $sc->execute([$idCaixa2]);
    $ok('o valor sai do caixa aberto', abs((float) $sc->fetchColumn() - (float) $pedido12['total']) < 0.01);

    echo PHP_EOL . 'Código de barras' . PHP_EOL;

    // 13) Regravar a matriz (ao salvar o estoque) não pode apagar os códigos.
    $varRepo = new ProductVariantRepository();
    $varRepo->setBarcodes($p11, ['Azul|M' => '7890000000011']);
    $varRepo->replaceForProduct($p11, product_variants_matrix_to_rows([
        'axis' => 'tamanho', 'colors' => ['Azul'], 'sizes' => ['P', 'M'], 'stock' => ['P' => ['Azul' => 9], 'M' => ['Azul' => 9]],
    ]));
    $sb = $pdo->prepare("SELECT barcode FROM product_variants WHERE product_id = ? AND variant_value = 'Azul|M'");
    $sb->execute([$p11]);
    $ok('o código da variação sobrevive à matriz regravada', $sb->fetchColumn() === '7890000000011');

    // 14) O mesmo código não pode valer para dois produtos da loja.
    $prodSvc = new App\Services\ProductService(new ProductRepository(), new StockMovementRepository());
    $ok('código de uma variação é recusado em outro produto', $recusa(fn () => $prodSvc->update($p12, $storeId, ['barcode' => '7890000000011'])));
    $prodSvc->update($p12, $storeId, ['barcode' => '7890000000012']);
    $ok('código livre é aceito', $repo->find($p12)['barcode'] === '7890000000012');
    $ok('o produto pode manter o próprio código', $prodSvc->update($p12, $storeId, ['barcode' => '7890000000012']) !== null);
} finally {
    // ------------------------------------------------------------- limpeza
    $pdo->exec("DELETE FROM stores WHERE slug LIKE '__conc_test__%'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '__conc_test__%'");
    echo PHP_EOL . 'dados de teste removidos' . PHP_EOL;
}

echo PHP_EOL . ($falhas === 0 ? 'TUDO PASSOU' : $falhas . ' VERIFICAÇÃO(ÕES) FALHARAM') . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
