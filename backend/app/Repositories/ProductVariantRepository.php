<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductVariantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function listByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_variants WHERE product_id = ? ORDER BY variant_type ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$productId]);

        return $stmt->fetchAll() ?: [];
    }

    public function replaceForProduct(int $productId, array $variants): void
    {
        $del = $this->pdo->prepare('DELETE FROM product_variants WHERE product_id = ?');
        $del->execute([$productId]);

        if ($variants === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO product_variants (product_id, variant_type, variant_value, stock_quantity, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $order = 0;
        foreach ($variants as $row) {
            $type = (string) ($row['variant_type'] ?? '');
            $value = trim((string) ($row['variant_value'] ?? ''));
            $stock = max(0, (int) ($row['stock_quantity'] ?? 0));
            if ($type === '' || $value === '') {
                continue;
            }
            $stmt->execute([$productId, $type, $value, $stock, $order++]);
        }
    }

    public function totalStock(int $productId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants WHERE product_id = ?');
        $stmt->execute([$productId]);

        return (int) $stmt->fetchColumn();
    }
}
