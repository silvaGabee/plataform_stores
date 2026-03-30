<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class OrderItemRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getByOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['order_id'],
            $data['product_id'],
            $data['quantity'],
            $data['price'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Retorna os order_id que têm itens com este produto. */
    public function getOrderIdsByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT order_id FROM order_items WHERE product_id = ?");
        $stmt->execute([$productId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Remove todos os itens de pedido que referenciam este produto (para permitir excluir o produto). */
    public function deleteByProductId(int $productId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM order_items WHERE product_id = ?");
        $stmt->execute([$productId]);
    }
}
