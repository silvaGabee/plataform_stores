<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductImageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, product_id, file_path, sort_order FROM product_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, product_id, file_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $productId, string $filePath, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO product_images (product_id, file_path, sort_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$productId, $filePath, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByProductId(int $productId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
    }
}
