<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductVitrineCategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** @return list<int> */
    public function listIdsByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT vitrine_category_id FROM product_vitrine_categories WHERE product_id = ? ORDER BY vitrine_category_id ASC'
        );
        $stmt->execute([$productId]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $id = (int) ($row['vitrine_category_id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** @param list<int> $categoryIds */
    public function replaceForProduct(int $productId, array $categoryIds): void
    {
        $seen = [];
        $unique = [];
        foreach ($categoryIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = $id;
        }

        $this->pdo->prepare('DELETE FROM product_vitrine_categories WHERE product_id = ?')->execute([$productId]);
        if ($unique === []) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_vitrine_categories (product_id, vitrine_category_id) VALUES (?, ?)'
        );
        foreach ($unique as $categoryId) {
            $stmt->execute([$productId, $categoryId]);
        }
    }
}
