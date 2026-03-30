<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class EmployeeGoalRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getByStoreAndPeriod(int $storeId, string $period): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT eg.user_id, eg.goal_amount FROM employee_goals eg WHERE eg.store_id = ? AND eg.period = ?"
        );
        $stmt->execute([$storeId, $period]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byUser = [];
        foreach ($rows as $r) {
            $byUser[(int) $r['user_id']] = (float) $r['goal_amount'];
        }
        return $byUser;
    }

    public function set(int $storeId, int $userId, string $period, float $goalAmount): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO employee_goals (store_id, user_id, period, goal_amount) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE goal_amount = VALUES(goal_amount)"
        );
        $stmt->execute([$storeId, $userId, $period, (float) $goalAmount]);
    }

    public function setBulk(int $storeId, string $period, array $userGoals): void
    {
        foreach ($userGoals as $userId => $amount) {
            $this->set($storeId, (int) $userId, $period, (float) $amount);
        }
    }
}
