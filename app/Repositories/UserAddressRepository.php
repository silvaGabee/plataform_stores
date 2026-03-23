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

    /** Verifica se o endereço pertence ao usuário. */
    public function belongsToUser(int $addressId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$addressId, $userId]);
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
}
