<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class PaymentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lê o pagamento travando a linha até o fim da transação (SELECT ... FOR UPDATE).
     *
     * É o que torna a confirmação idempotente sob concorrência: dois pedidos de
     * confirmação simultâneos não conseguem ler "pendente" ao mesmo tempo — o
     * segundo espera o primeiro terminar e então enxerga "confirmado". Sem o
     * lock, ambos passavam pela checagem de status e o estoque era baixado duas
     * vezes para a mesma venda.
     *
     * Só faz sentido dentro de Database::transaction(); fora dela o InnoDB
     * libera o lock no fim da própria instrução.
     */
    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getPendingByOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'pendente' LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }

    public function listPendingByStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, o.total as order_total FROM payments p JOIN orders o ON o.id = p.order_id 
             WHERE p.store_id = ? AND p.status = 'pendente' ORDER BY p.created_at DESC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments (order_id, store_id, method, status, amount, pix_qr_code, card_holder, card_last4, card_brand)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['order_id'],
            $data['store_id'],
            $data['method'],
            $data['status'] ?? 'pendente',
            $data['amount'],
            $data['pix_qr_code'] ?? null,
            $data['card_holder'] ?? null,
            $data['card_last4'] ?? null,
            $data['card_brand'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Vendas de PDV pagas desde um instante (abertura do caixa), por forma de
     * pagamento. Só dinheiro entra na gaveta — cartão e PIX aparecem aqui para
     * o operador conferir o turno inteiro no fechamento.
     *
     * @return list<array{method: string, count: int, total: float, discount: float}>
     */
    public function pdvTotalsByMethodSince(int $storeId, string $since): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.method,
                    COUNT(DISTINCT o.id) AS count,
                    COALESCE(SUM(p.amount), 0) AS total,
                    COALESCE(SUM(o.discount), 0) AS discount
               FROM payments p
               JOIN orders o ON o.id = p.order_id
              WHERE o.store_id = ?
                AND o.order_type = 'pdv'
                AND p.status = 'confirmado'
                AND o.created_at >= ?
              GROUP BY p.method"
        );
        $stmt->execute([$storeId, $since]);

        return array_map(static fn (array $r): array => [
            'method' => (string) $r['method'],
            'count' => (int) $r['count'],
            'total' => round((float) $r['total'], 2),
            'discount' => round((float) $r['discount'], 2),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Últimas vendas de PDV pagas desde um instante, com a forma de pagamento.
     *
     * @return list<array<string, mixed>>
     */
    public function pdvRecentSalesSince(int $storeId, string $since, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.id, o.total, o.discount, o.pdv_customer_name, o.created_at,
                    p.method, u.name AS operator_name,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS items
               FROM orders o
               JOIN payments p ON p.order_id = o.id AND p.status = 'confirmado'
               LEFT JOIN users u ON u.id = o.created_by
              WHERE o.store_id = ?
                AND o.order_type = 'pdv'
                AND o.created_at >= ?
              ORDER BY o.id DESC
              LIMIT " . max(1, min(50, $limit))
        );
        $stmt->execute([$storeId, $since]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
