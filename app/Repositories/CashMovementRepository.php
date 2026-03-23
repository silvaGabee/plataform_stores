<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class CashMovementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cash_movements (cash_register_id, order_id, type, amount, description) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['cash_register_id'],
            $data['order_id'] ?? null,
            $data['type'],
            $data['amount'],
            $data['description'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByCashRegister(int $cashRegisterId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cash_movements WHERE cash_register_id = ? ORDER BY created_at"
        );
        $stmt->execute([$cashRegisterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
