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

    /**
     * Fecha o caixa guardando o que foi contado, o que o sistema esperava
     * (fundo + entradas − saídas em dinheiro), a diferença e quem fechou.
     */
    public function close(int $id, float $finalAmount, ?float $expected = null, ?int $closedBy = null): bool
    {
        $diff = $expected !== null ? round($finalAmount - $expected, 2) : null;
        $stmt = $this->pdo->prepare("UPDATE cash_registers SET final_amount = ?, expected_amount = ?, difference = ?, closed_by = ?, closed_at = NOW() WHERE id = ? AND closed_at IS NULL");
        $stmt->execute([$finalAmount, $expected, $diff, $closedBy, $id]);

        return $stmt->rowCount() === 1;
    }

    /** Últimos fechamentos com nomes de quem abriu e fechou. */
    public function listClosedWithPeople(int $storeId, int $limit = 15): array
    {
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT c.*, ua.name AS opened_by_name, uf.name AS closed_by_name
             FROM cash_registers c
             LEFT JOIN users ua ON ua.id = c.opened_by
             LEFT JOIN users uf ON uf.id = c.closed_by
             WHERE c.store_id = ? AND c.closed_at IS NOT NULL
             ORDER BY c.closed_at DESC LIMIT {$limit}"
        );
        $st->execute([$storeId]);

        return $st->fetchAll();
    }
}
