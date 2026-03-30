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
            "INSERT INTO orders (store_id, customer_id, created_by, order_type, delivery_type, address_id, status, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
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
}
