<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StoreGoalRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getByStoreAndPeriod(int $storeId, string $period): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, store_id, period, goal_amount, updated_at FROM store_goals WHERE store_id = ? AND period = ?"
        );
        $stmt->execute([$storeId, $period]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function set(int $storeId, string $period, float $goalAmount): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO store_goals (store_id, period, goal_amount) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE goal_amount = VALUES(goal_amount)"
        );
        $stmt->execute([$storeId, $period, (float) $goalAmount]);
    }
}
