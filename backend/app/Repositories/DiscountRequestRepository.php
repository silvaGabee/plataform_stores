<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Pedido de desconto do PDV: o funcionário propõe, o gerente aprova ou recusa.
 *
 * Fluxo: pendente → aprovado | recusado | cancelado; aprovado → usado (quando a
 * venda é finalizada com exatamente aquele desconto e aquele subtotal).
 */
class DiscountRequestRepository
{
    /** Pedido sem resposta por mais que isso deixa de valer. */
    public const VALIDADE_MINUTOS = 15;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $d): int
    {
        $st = $this->pdo->prepare('INSERT INTO discount_requests (store_id, requested_by, subtotal, discount, reason, items_summary) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$d['store_id'], $d['requested_by'], $d['subtotal'], $d['discount'], $d['reason'], $d['items_summary']]);

        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id, int $storeId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT d.*, u.name AS requested_by_name, g.name AS decided_by_name
             FROM discount_requests d
             LEFT JOIN users u ON u.id = d.requested_by
             LEFT JOIN users g ON g.id = d.decided_by
             WHERE d.id = ? AND d.store_id = ?'
        );
        $st->execute([$id, $storeId]);
        $r = $st->fetch();

        return $r ?: null;
    }

    /** Pendentes ainda válidos (para o gerente decidir). */
    public function listPending(int $storeId): array
    {
        $this->expire($storeId);
        $st = $this->pdo->prepare(
            "SELECT d.*, u.name AS requested_by_name FROM discount_requests d
             LEFT JOIN users u ON u.id = d.requested_by
             WHERE d.store_id = ? AND d.status = 'pendente' ORDER BY d.created_at ASC"
        );
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    /** Últimas decisões, para o gerente consultar. */
    public function listRecent(int $storeId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT d.*, u.name AS requested_by_name, g.name AS decided_by_name FROM discount_requests d
             LEFT JOIN users u ON u.id = d.requested_by
             LEFT JOIN users g ON g.id = d.decided_by
             WHERE d.store_id = ? AND d.status <> 'pendente' ORDER BY d.id DESC LIMIT {$limit}"
        );
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    public function decide(int $id, int $storeId, string $status, int $managerId): bool
    {
        if (!in_array($status, ['aprovado', 'recusado'], true)) {
            return false;
        }
        $st = $this->pdo->prepare("UPDATE discount_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND store_id = ? AND status = 'pendente'");
        $st->execute([$status, $managerId, $id, $storeId]);

        return $st->rowCount() === 1;
    }

    public function cancel(int $id, int $storeId, int $requesterId): bool
    {
        $st = $this->pdo->prepare("UPDATE discount_requests SET status = 'cancelado' WHERE id = ? AND store_id = ? AND requested_by = ? AND status IN ('pendente','aprovado')");
        $st->execute([$id, $storeId, $requesterId]);

        return $st->rowCount() === 1;
    }

    /** Marca como usado; só passa se estava aprovado (uso único). */
    public function markUsed(int $id, int $storeId, int $orderId): bool
    {
        $st = $this->pdo->prepare("UPDATE discount_requests SET status = 'usado', order_id = ? WHERE id = ? AND store_id = ? AND status = 'aprovado'");
        $st->execute([$orderId, $id, $storeId]);

        return $st->rowCount() === 1;
    }

    public function expire(int $storeId): void
    {
        $min = self::VALIDADE_MINUTOS;
        $st = $this->pdo->prepare("UPDATE discount_requests SET status = 'cancelado' WHERE store_id = ? AND status = 'pendente' AND created_at < NOW() - INTERVAL {$min} MINUTE");
        $st->execute([$storeId]);
    }
}
