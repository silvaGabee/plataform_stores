<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StockMovementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_movements (store_id, product_id, user_id, type, quantity, reason) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['product_id'],
            $data['user_id'] ?? null,
            $data['type'],
            $data['quantity'],
            $data['reason'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByProduct(int $productId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name FROM stock_movements sm 
             JOIN products p ON p.id = sm.product_id 
             WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT ?"
        );
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    }

    public function listByStore(int $storeId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name FROM stock_movements sm 
             JOIN products p ON p.id = sm.product_id 
             WHERE sm.store_id = ? ORDER BY sm.created_at DESC LIMIT ?"
        );
        $stmt->execute([$storeId, $limit]);
        return $stmt->fetchAll();
    }
}
