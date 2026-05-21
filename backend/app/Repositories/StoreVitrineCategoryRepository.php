<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StoreVitrineCategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function listByStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM store_vitrine_categories WHERE store_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$storeId]);

        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM store_vitrine_categories WHERE id = ? AND store_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $storeId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function nameExists(int $storeId, string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM store_vitrine_categories WHERE store_id = ? AND LOWER(name) = LOWER(?)';
        $params = [$storeId, $name];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function countByStore(int $storeId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM store_vitrine_categories WHERE store_id = ?');
        $stmt->execute([$storeId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $storeId, string $name, string $iconKey, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO store_vitrine_categories (store_id, name, icon_key, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$storeId, $name, $iconKey, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $storeId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM store_vitrine_categories WHERE id = ? AND store_id = ?');

        return $stmt->execute([$id, $storeId]);
    }
}
