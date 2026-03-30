<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class RoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE store_id = ? ORDER BY name");
        $stmt->execute([$storeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $parentId = isset($data['parent_role_id']) ? $data['parent_role_id'] : null;
        if ($parentId !== null && (int) $parentId < 1) {
            $parentId = null;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO roles (store_id, name, parent_role_id) VALUES (?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['store_id'],
            (string) $data['name'],
            $parentId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("UPDATE roles SET name = ?, parent_role_id = ? WHERE id = ?");
        return $stmt->execute([
            $data['name'],
            $data['parent_role_id'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getHierarchy(int $storeId): array
    {
        $roles = $this->listByStore($storeId);
        $byId = [];
        foreach ($roles as $r) {
            $byId[$r['id']] = $r;
            $byId[$r['id']]['children'] = [];
        }
        $root = [];
        foreach ($byId as $id => $r) {
            if (empty($r['parent_role_id'])) {
                $root[] = &$byId[$id];
            } else {
                $pid = (int) $r['parent_role_id'];
                $idInt = (int) $id;
                if ($pid === $idInt || !isset($byId[$pid])) {
                    $root[] = &$byId[$id];
                } else {
                    $byId[$pid]['children'][] = &$byId[$id];
                }
            }
        }
        return $root;
    }
}
