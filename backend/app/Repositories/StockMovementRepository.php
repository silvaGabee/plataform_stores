<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StockMovementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_movements (store_id, product_id, user_id, type, quantity, reason) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['product_id'],
            $data['user_id'] ?? null,
            $data['type'],
            $data['quantity'],
            $data['reason'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * LIMIT não aceita parâmetro vinculado: o PDO envia o valor como string e o
     * MySQL recebe LIMIT '50', que é erro de sintaxe. Estes dois métodos
     * respondiam 500 desde sempre. O limite é forçado a inteiro e interpolado —
     * não vem do usuário, e o cast fecha qualquer injeção.
     */
    private static function limite(int $limit, int $padrao): int
    {
        return $limit > 0 ? min($limit, 500) : $padrao;
    }

    public function listByProduct(int $productId, int $limit = 50): array
    {
        $limit = self::limite($limit, 50);
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function listByStore(int $storeId, int $limit = 100): array
    {
        $limit = self::limite($limit, 100);
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             WHERE sm.store_id = ? ORDER BY sm.created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }
}
