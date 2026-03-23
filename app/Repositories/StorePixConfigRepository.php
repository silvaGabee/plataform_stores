<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StorePixConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findByStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM store_pix_configs WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO store_pix_configs (store_id, pix_key, pix_key_type, merchant_name, merchant_city, provider) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['pix_key'] ?? null,
            $data['pix_key_type'] ?? 'aleatoria',
            $data['merchant_name'] ?? null,
            $data['merchant_city'] ?? null,
            $data['provider'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $storeId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE store_pix_configs SET pix_key = ?, pix_key_type = ?, merchant_name = ?, merchant_city = ?, provider = ? WHERE store_id = ?"
        );
        return $stmt->execute([
            $data['pix_key'] ?? null,
            $data['pix_key_type'] ?? 'aleatoria',
            $data['merchant_name'] ?? null,
            $data['merchant_city'] ?? null,
            $data['provider'] ?? null,
            $storeId,
        ]);
    }

    public function getOrCreateForStore(int $storeId): array
    {
        $config = $this->findByStore($storeId);
        if ($config) return $config;
        $this->create(['store_id' => $storeId]);
        return $this->findByStore($storeId);
    }
}
