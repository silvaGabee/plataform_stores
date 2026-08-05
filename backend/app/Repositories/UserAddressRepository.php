<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class UserAddressRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_addresses WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Endereços de vários registros de `users` de uma vez.
     *
     * Existe porque a mesma pessoa hoje tem uma linha em `users` por loja, e os
     * endereços ficaram espalhados entre elas. Some quando a identidade for
     * unificada (Fase 3 do plano em docs/).
     *
     * @param int[] $userIds
     */
    public function getByUserIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM user_addresses WHERE user_id IN ({$placeholders}) ORDER BY id ASC"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    /**
     * O endereço pertence a algum destes usuários?
     *
     * @param int[] $userIds
     */
    public function belongsToAnyUser(int $addressId, array $userIds): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM user_addresses WHERE id = ? AND user_id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$addressId], $ids));

        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_addresses (user_id, label, street, number, complement, neighborhood, city, state, zipcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'],
            $data['label'] ?? null,
            $data['street'],
            $data['number'],
            $data['complement'] ?? null,
            $data['neighborhood'] ?? null,
            $data['city'],
            $data['state'],
            $data['zipcode'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_addresses SET label = ?, street = ?, number = ?, complement = ?, neighborhood = ?, city = ?, state = ?, zipcode = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['label'] ?? null,
            $data['street'],
            $data['number'],
            $data['complement'] ?? null,
            $data['neighborhood'] ?? null,
            $data['city'],
            $data['state'],
            $data['zipcode'],
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_addresses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
