<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdAndStore(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $storeId]);
        return $stmt->fetch() ?: null;
    }

    public function listByStore(int $storeId, bool $onlyWithStock = false): array
    {
        $sql = "SELECT * FROM products WHERE store_id = ?";
        if ($onlyWithStock) $sql .= " AND stock_quantity > 0";
        $sql .= " ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function listLowStock(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM products WHERE store_id = ? AND min_stock > 0 AND stock_quantity <= min_stock ORDER BY stock_quantity ASC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO products (store_id, name, description, cost_price, sale_price, stock_quantity, min_stock) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['cost_price'] ?? 0,
            $data['sale_price'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['min_stock'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE products SET name = ?, description = ?, cost_price = ?, sale_price = ?, stock_quantity = ?, min_stock = ? WHERE id = ?"
        );
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['cost_price'] ?? 0,
            $data['sale_price'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['min_stock'] ?? 0,
            $id,
        ]);
    }

    public function updateStock(int $id, int $quantity): bool
    {
        $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
