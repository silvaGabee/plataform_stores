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
            "INSERT INTO orders (store_id, customer_id, created_by, order_type, delivery_type, address_id, status, total, access_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
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

    /** Recalcula o total do pedido a partir dos itens restantes. */
    public function recalcTotal(int $orderId): void
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity * price), 0) FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $total = (float) $stmt->fetchColumn();
        $up = $this->pdo->prepare("UPDATE orders SET total = ? WHERE id = ?");
        $up->execute([$total, $orderId]);
    }

    /** Lista pedidos pagos para a aba Entregas: em andamento todos; entregues só os 5 mais recentes. */
    public function listPaidForEntregas(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id 
             WHERE o.store_id = ? AND o.status = 'pago' 
             AND (o.delivery_stage IS NULL OR o.delivery_stage != 'entregue') 
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$storeId]);
        $notDelivered = $stmt->fetchAll();

        $stmt2 = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id 
             WHERE o.store_id = ? AND o.status = 'pago' AND o.delivery_stage = 'entregue' 
             ORDER BY o.created_at DESC LIMIT 5"
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

    /** Remove pedido da loja (itens e pagamentos em cascata). */
    public function deleteByIdAndStore(int $orderId, int $storeId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM orders WHERE id = ? AND store_id = ?");
        return $stmt->execute([$orderId, $storeId]);
    }

    /** Lista pedidos do cliente na loja que ainda não estão como entregues (para "Meus pedidos"). */
    public function listByCustomerNotDelivered(int $storeId, int $customerId): array
    {
        return $this->listByCustomersNotDelivered($storeId, [$customerId]);
    }

    /**
     * Idem, aceitando vários registros de `users` da mesma pessoa — hoje ela
     * tem uma linha por loja, então seus pedidos ficam divididos entre elas.
     *
     * @param int[] $customerIds
     */
    public function listByCustomersNotDelivered(int $storeId, array $customerIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON u.id = o.customer_id
             WHERE o.store_id = ? AND o.customer_id IN ({$placeholders}) AND o.status = 'pago'
             AND (o.delivery_stage IS NULL OR o.delivery_stage != 'entregue')
             ORDER BY o.created_at DESC"
        );
        $stmt->execute(array_merge([$storeId], $ids));

        return $stmt->fetchAll();
    }
}
