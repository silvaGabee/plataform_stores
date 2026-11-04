<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class OrderRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdAndStore(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $storeId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lê o pedido travando a linha até o fim da transação.
     * Use antes de decidir algo com base em orders.status — sem o lock, dois
     * pedidos de pagamento simultâneos leem "pendente" ao mesmo tempo e ambos
     * criam pagamento para o mesmo pedido.
     */
    public function findByIdAndStoreForUpdate(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? AND store_id = ? FOR UPDATE");
        $stmt->execute([$id, $storeId]);

        return $stmt->fetch() ?: null;
    }

    /** Pedido com nome do cliente (para exibição no Kanban). */
    public function findByIdAndStoreWithCustomer(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id WHERE o.id = ? AND o.store_id = ?"
        );
        $stmt->execute([$id, $storeId]);
        return $stmt->fetch() ?: null;
    }

    public function listByStore(int $storeId, ?string $status = null, ?string $orderType = null, ?int $limit = null): array
    {
        $sql = "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id WHERE o.store_id = ?";
        $params = [$storeId];
        if ($status !== null) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        if ($orderType !== null) {
            $sql .= " AND o.order_type = ?";
            $params[] = $orderType;
        }
        $sql .= " ORDER BY o.created_at DESC";
        if ($limit !== null) $sql .= " LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (store_id, customer_id, created_by, order_type, delivery_type, address_id, status, total, discount, pdv_customer_name, access_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['customer_id'],
            $data['created_by'] ?? null,
            $data['order_type'],
            $data['delivery_type'] ?? 'retirada',
            $data['address_id'] ?? null,
            $data['status'] ?? 'pendente',
            $data['total'] ?? 0,
            $data['discount'] ?? 0,
            $data['pdv_customer_name'] ?? null,
            // Segredo do link do comprovante: é o que substitui o id sequencial
            // como prova de que quem abriu a página é quem fez o pedido.
            bin2hex(random_bytes(16)),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Recalcula o total do pedido a partir dos itens restantes.
     *
     * O desconto do PDV continua valendo, mas nunca deixa o total negativo:
     * se os itens restantes valem menos que o desconto, ele é reduzido junto.
     */
    public function recalcTotal(int $orderId): void
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity * price), 0) FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $subtotal = (float) $stmt->fetchColumn();
        $up = $this->pdo->prepare(
            "UPDATE orders
                SET discount = LEAST(discount, ?),
                    total = ? - LEAST(discount, ?)
              WHERE id = ?"
        );
        $up->execute([$subtotal, $subtotal, $subtotal, $orderId]);
    }

    /** Lista pedidos pagos para a aba Entregas: em andamento todos; entregues só os 5 mais recentes. */
    public function listPaidForEntregas(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id
             WHERE o.store_id = ? AND o.status = 'pago' AND o.order_type = 'online'
             AND (o.delivery_stage IS NULL OR o.delivery_stage != 'entregue')
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$storeId]);
        $notDelivered = $stmt->fetchAll();

        $stmt2 = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id
             WHERE o.store_id = ? AND o.status = 'pago' AND o.order_type = 'online' AND o.delivery_stage = 'entregue'
             ORDER BY o.created_at DESC LIMIT 8"
        );
        $stmt2->execute([$storeId]);
        $delivered = $stmt2->fetchAll();

        return array_merge($notDelivered, $delivered);
    }

    /** Atualiza estágio de entrega (e opcionalmente tracking_code). */
    public function updateDeliveryStage(int $orderId, int $storeId, string $stage, ?string $trackingCode = null): bool
    {
        $allowed = ['solicitado', 'empacotando', 'entregue_transportadora', 'em_rota', 'entregue'];
        if (!in_array($stage, $allowed, true)) return false;
        $stmt = $this->pdo->prepare("UPDATE orders SET delivery_stage = ?, tracking_code = COALESCE(?, tracking_code) WHERE id = ? AND store_id = ?");
        return $stmt->execute([$stage, $trackingCode, $orderId, $storeId]);
    }

    /** Lista pedidos do cliente na loja que ainda não estão como entregues (para "Meus pedidos"). */
    public function listByCustomerNotDelivered(int $storeId, int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id
             WHERE o.store_id = ? AND o.customer_id = ? AND o.status = 'pago'
             AND (o.delivery_stage IS NULL OR o.delivery_stage != 'entregue')
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$storeId, $customerId]);

        return $stmt->fetchAll();
    }
    /**
     * Quantidade segurada por pedidos online ainda dentro do prazo de
     * pagamento, por produto e variação: [product_id][variant_key|''] => qtd.
     *
     * É o que impede o PDV (ou outro cliente) de vender a peça que alguém já
     * está pagando por PIX.
     *
     * @return array<int, array<string, int>>
     */
    public function reservedQuantities(int $storeId, ?int $exceptOrderId = null): array
    {
        $sql = "SELECT oi.product_id, COALESCE(oi.variant_key, '') AS vk, SUM(oi.quantity) AS q
                FROM order_items oi JOIN orders o ON o.id = oi.order_id
                WHERE o.store_id = ? AND o.status = 'pendente' AND o.order_type = 'online'
                  AND o.expires_at IS NOT NULL AND o.expires_at > NOW()";
        $args = [$storeId];
        if ($exceptOrderId) {
            $sql .= ' AND o.id <> ?';
            $args[] = $exceptOrderId;
        }
        $st = $this->pdo->prepare($sql . ' GROUP BY oi.product_id, vk');
        $st->execute($args);
        $map = [];
        foreach ($st->fetchAll() as $r) {
            $map[(int) $r['product_id']][(string) $r['vk']] = (int) $r['q'];
        }

        return $map;
    }

    public function setExpiresAt(int $orderId, ?string $mysqlInterval): void
    {
        if ($mysqlInterval === null) {
            $this->pdo->prepare('UPDATE orders SET expires_at = NULL WHERE id = ?')->execute([$orderId]);

            return;
        }
        // O intervalo vem de constantes do OrderService, nunca do usuário.
        $this->pdo->prepare("UPDATE orders SET expires_at = NOW() + INTERVAL {$mysqlInterval} WHERE id = ?")->execute([$orderId]);
    }

    /**
     * Cancela os pedidos pendentes cujo prazo de pagamento acabou, junto com o
     * pagamento em aberto. Devolve quantos foram cancelados.
     */
    /** @return list<string> cupons dos pedidos expirados (para devolver o uso) */
    public function expirePending(int $storeId): array
    {
        $st = $this->pdo->prepare("SELECT id, coupon_code FROM orders WHERE store_id = ? AND status = 'pendente' AND expires_at IS NOT NULL AND expires_at <= NOW()");
        $st->execute([$storeId]);
        $rows = $st->fetchAll();
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $in = implode(',', $ids);
        $this->pdo->exec("UPDATE orders SET status = 'cancelado', cancelled_at = NOW(), cancelled_by = NULL WHERE id IN ({$in}) AND status = 'pendente'");
        $this->pdo->exec("UPDATE payments SET status = 'cancelado' WHERE order_id IN ({$in}) AND status = 'pendente'");

        return array_values(array_filter(array_map(static fn ($r) => (string) ($r['coupon_code'] ?? ''), $rows)));
    }

    /** Cancela um pedido pendente. Devolve false se ele já não estava pendente. */
    public function cancelPending(int $orderId, int $storeId, ?int $byUserId): bool
    {
        $st = $this->pdo->prepare("UPDATE orders SET status = 'cancelado', cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND store_id = ? AND status = 'pendente'");
        $st->execute([$byUserId, $orderId, $storeId]);
        if ($st->rowCount() !== 1) {
            return false;
        }
        $this->pdo->prepare("UPDATE payments SET status = 'cancelado' WHERE order_id = ? AND status = 'pendente'")->execute([$orderId]);

        return true;
    }

    public function setCoupon(int $orderId, ?string $code): void
    {
        $this->pdo->prepare('UPDATE orders SET coupon_code = ? WHERE id = ?')->execute([$code, $orderId]);
    }

    /**
     * Pedidos online aguardando pagamento (aba Entregas do gerente), com o
     * pagamento em aberto e o nome do cliente.
     */
    public function listAwaitingPayment(int $storeId): array
    {
        $st = $this->pdo->prepare(
            "SELECT o.id, o.total, o.discount, o.delivery_type, o.created_at, o.expires_at, u.name AS customer_name,
                    p.id AS payment_id, p.method AS payment_method, p.amount AS payment_amount, p.installments
             FROM orders o
             LEFT JOIN users u ON u.id = o.customer_id
             LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'pendente'
             WHERE o.store_id = ? AND o.status = 'pendente' AND o.order_type = 'online'
             ORDER BY o.created_at ASC"
        );
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    /** Todos os pedidos do cliente nesta loja, do mais recente ao mais antigo. */
    public function listByCustomer(int $storeId, int $customerId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            "SELECT o.*, p.id AS payment_id, p.method AS payment_method, p.status AS payment_status
             FROM orders o
             LEFT JOIN payments p ON p.id = (SELECT p2.id FROM payments p2 WHERE p2.order_id = o.id ORDER BY (p2.status = 'confirmado') DESC, p2.id DESC LIMIT 1)
             WHERE o.store_id = ? AND o.customer_id = ? AND o.order_type = 'online'
             ORDER BY o.created_at DESC, o.id DESC LIMIT {$limit}"
        );
        $st->execute([$storeId, $customerId]);

        return $st->fetchAll();
    }
}
