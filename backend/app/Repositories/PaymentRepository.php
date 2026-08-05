<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class PaymentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lê o pagamento travando a linha até o fim da transação (SELECT ... FOR UPDATE).
     *
     * É o que torna a confirmação idempotente sob concorrência: dois pedidos de
     * confirmação simultâneos não conseguem ler "pendente" ao mesmo tempo — o
     * segundo espera o primeiro terminar e então enxerga "confirmado". Sem o
     * lock, ambos passavam pela checagem de status e o estoque era baixado duas
     * vezes para a mesma venda.
     *
     * Só faz sentido dentro de Database::transaction(); fora dela o InnoDB
     * libera o lock no fim da própria instrução.
     */
    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getPendingByOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'pendente' LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }

    public function listPendingByStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, o.total as order_total FROM payments p JOIN orders o ON o.id = p.order_id 
             WHERE p.store_id = ? AND p.status = 'pendente' ORDER BY p.created_at DESC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments (order_id, store_id, method, status, amount, pix_qr_code, card_holder, card_last4, card_brand)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['order_id'],
            $data['store_id'],
            $data['method'],
            $data['status'] ?? 'pendente',
            $data['amount'],
            $data['pix_qr_code'] ?? null,
            $data['card_holder'] ?? null,
            $data['card_last4'] ?? null,
            $data['card_brand'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function updatePixQr(int $id, ?string $qrCode): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET pix_qr_code = ? WHERE id = ?");
        return $stmt->execute([$qrCode, $id]);
    }
}
