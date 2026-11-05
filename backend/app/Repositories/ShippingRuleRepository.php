<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/** Faixas de CEP com valor e prazo de entrega (frete da loja). */
class ShippingRuleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** @return list<array<string, mixed>> */
    public function listByStore(int $storeId): array
    {
        $st = $this->pdo->prepare('SELECT id, name, cep_start, cep_end, price, days FROM store_shipping_rules WHERE store_id = ? ORDER BY cep_start, cep_end, id');
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    public function create(int $storeId, string $name, string $cepStart, string $cepEnd, float $price, ?int $days): int
    {
        $st = $this->pdo->prepare('INSERT INTO store_shipping_rules (store_id, name, cep_start, cep_end, price, days) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$storeId, $name, $cepStart, $cepEnd, $price, $days]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $storeId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM store_shipping_rules WHERE id = ? AND store_id = ?');
        $st->execute([$id, $storeId]);

        return $st->rowCount() > 0;
    }
}
