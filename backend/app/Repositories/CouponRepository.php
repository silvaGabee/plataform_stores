<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/** Cupons de desconto da vitrine (gerenciados pelo gerente). */
class CouponRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)) ?? '');
    }

    public function listByStore(int $storeId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM coupons WHERE store_id = ? ORDER BY active DESC, created_at DESC');
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    public function findByCode(int $storeId, string $code): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM coupons WHERE store_id = ? AND code = ?');
        $st->execute([$storeId, self::normalizeCode($code)]);
        $r = $st->fetch();

        return $r ?: null;
    }

    public function find(int $id, int $storeId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM coupons WHERE id = ? AND store_id = ?');
        $st->execute([$id, $storeId]);
        $r = $st->fetch();

        return $r ?: null;
    }

    public function create(int $storeId, array $d): int
    {
        $st = $this->pdo->prepare('INSERT INTO coupons (store_id, code, type, value, min_subtotal, max_uses, ends_at, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $st->execute([$storeId, $d['code'], $d['type'], $d['value'], $d['min_subtotal'], $d['max_uses'], $d['ends_at']]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setActive(int $id, int $storeId, bool $active): bool
    {
        $st = $this->pdo->prepare('UPDATE coupons SET active = ? WHERE id = ? AND store_id = ?');

        return $st->execute([$active ? 1 : 0, $id, $storeId]);
    }

    public function delete(int $id, int $storeId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM coupons WHERE id = ? AND store_id = ?');

        return $st->execute([$id, $storeId]);
    }

    /**
     * Consome um uso do cupom de forma atômica: dois checkouts simultâneos não
     * passam juntos do limite de usos.
     */
    public function consume(int $id): bool
    {
        $st = $this->pdo->prepare('UPDATE coupons SET uses = uses + 1 WHERE id = ? AND active = 1 AND (max_uses IS NULL OR uses < max_uses)');
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }

    /** Devolve o uso quando o pedido com cupom é cancelado ou expira. */
    public function release(int $storeId, string $code): void
    {
        $st = $this->pdo->prepare('UPDATE coupons SET uses = GREATEST(uses - 1, 0) WHERE store_id = ? AND code = ?');
        $st->execute([$storeId, $code]);
    }

    /**
     * Calcula o desconto do cupom para um subtotal, ou lança com o motivo.
     *
     * @return array{coupon: array, discount: float}
     */
    public function evaluate(int $storeId, string $code, float $subtotal): array
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            throw new \InvalidArgumentException('Informe o código do cupom.');
        }
        $c = $this->findByCode($storeId, $code);
        if (!$c || !(int) $c['active']) {
            throw new \InvalidArgumentException('Cupom inválido ou desativado.');
        }
        if (!empty($c['ends_at']) && $c['ends_at'] < date('Y-m-d')) {
            throw new \InvalidArgumentException('Este cupom expirou.');
        }
        if ($c['max_uses'] !== null && (int) $c['uses'] >= (int) $c['max_uses']) {
            throw new \InvalidArgumentException('Este cupom atingiu o limite de usos.');
        }
        if ($subtotal < (float) $c['min_subtotal']) {
            throw new \InvalidArgumentException('Cupom válido para compras a partir de R$ ' . number_format((float) $c['min_subtotal'], 2, ',', '.') . '.');
        }
        $desconto = $c['type'] === 'percent'
            ? round($subtotal * ((float) $c['value'] / 100), 2)
            : round((float) $c['value'], 2);
        // Nunca zera a compra: no máximo 90% do subtotal.
        $desconto = min($desconto, round($subtotal * 0.9, 2));

        return ['coupon' => $c, 'discount' => $desconto];
    }
}
