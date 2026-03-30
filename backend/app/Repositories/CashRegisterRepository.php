<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class CashRegisterRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findOpenByStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cash_registers WHERE store_id = ? AND closed_at IS NULL ORDER BY opened_at DESC LIMIT 1"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetch() ?: null;
    }

    public function listByStore(int $storeId, ?int $limit = 30): array
    {
        $sql = "SELECT * FROM cash_registers WHERE store_id = ? ORDER BY opened_at DESC";
        if ($limit) $sql .= " LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function open(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cash_registers (store_id, opened_by, initial_amount) VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['opened_by'],
            $data['initial_amount'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function close(int $id, float $finalAmount): bool
    {
        $stmt = $this->pdo->prepare("UPDATE cash_registers SET final_amount = ?, closed_at = NOW() WHERE id = ?");
        return $stmt->execute([$finalAmount, $id]);
    }
}
