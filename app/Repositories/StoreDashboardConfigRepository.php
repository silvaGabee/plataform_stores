<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StoreDashboardConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getByStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT store_id, widgets_config, updated_at FROM store_dashboard_config WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['widgets_config'])) {
            $row['widgets'] = json_decode($row['widgets_config'], true);
            if (!is_array($row['widgets'])) {
                $row['widgets'] = [];
            }
        } else {
            $row['widgets'] = [];
        }
        return $row;
    }

    public function setWidgets(int $storeId, array $widgets): void
    {
        $json = json_encode($widgets, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            "INSERT INTO store_dashboard_config (store_id, widgets_config) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE widgets_config = VALUES(widgets_config)"
        );
        $stmt->execute([$storeId, $json]);
    }
}
