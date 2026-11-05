<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Avaliações de produto (1 a 5 estrelas).
 *
 * Só avalia quem comprou: precisa existir um pedido pago (ou já enviado) do
 * cliente, nesta loja, com o produto. Cada cliente tem uma avaliação por
 * produto — avaliar de novo substitui a anterior.
 */
class ReviewRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function userBoughtProduct(int $userId, int $storeId, int $productId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.store_id = ? AND o.customer_id = ? AND oi.product_id = ? AND o.status IN ('pago', 'enviado')
             LIMIT 1"
        );
        $st->execute([$storeId, $userId, $productId]);

        return (bool) $st->fetchColumn();
    }

    public function save(int $storeId, int $productId, int $userId, int $rating, ?string $comment): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO product_reviews (store_id, product_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([$storeId, $productId, $userId, $rating, $comment]);
    }

    public function findByUser(int $productId, int $userId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM product_reviews WHERE product_id = ? AND user_id = ?');
        $st->execute([$productId, $userId]);

        return $st->fetch() ?: null;
    }

    /**
     * Avaliações do produto, mais recentes primeiro. Do nome do cliente só vai
     * o primeiro nome — a vitrine é pública.
     *
     * @return list<array{rating: int, comment: ?string, created_at: string, autor: string}>
     */
    public function listByProduct(int $productId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT r.rating, r.comment, r.updated_at AS created_at, u.name
             FROM product_reviews r INNER JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? ORDER BY r.updated_at DESC, r.id DESC LIMIT {$limit}"
        );
        $st->execute([$productId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $primeiro = trim(explode(' ', trim((string) $row['name']))[0] ?? '');
            $out[] = [
                'rating' => (int) $row['rating'],
                'comment' => $row['comment'] !== null && $row['comment'] !== '' ? (string) $row['comment'] : null,
                'created_at' => (string) $row['created_at'],
                'autor' => $primeiro !== '' ? $primeiro : 'Cliente',
            ];
        }

        return $out;
    }

    /**
     * Média e quantidade por produto da loja (uma consulta para a vitrine toda).
     *
     * @return array<int, array{media: float, total: int}>
     */
    public function summaryByStore(int $storeId): array
    {
        $st = $this->pdo->prepare('SELECT product_id, AVG(rating) AS media, COUNT(*) AS total FROM product_reviews WHERE store_id = ? GROUP BY product_id');
        $st->execute([$storeId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int) $row['product_id']] = ['media' => round((float) $row['media'], 1), 'total' => (int) $row['total']];
        }

        return $out;
    }

    /** @return array{media: float, total: int, distribuicao: array<int, int>} */
    public function summaryByProduct(int $productId): array
    {
        $st = $this->pdo->prepare('SELECT rating, COUNT(*) AS n FROM product_reviews WHERE product_id = ? GROUP BY rating');
        $st->execute([$productId]);
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $soma = 0;
        $total = 0;
        foreach ($st->fetchAll() as $row) {
            $r = (int) $row['rating'];
            $n = (int) $row['n'];
            if (isset($dist[$r])) {
                $dist[$r] = $n;
                $soma += $r * $n;
                $total += $n;
            }
        }

        return ['media' => $total > 0 ? round($soma / $total, 1) : 0.0, 'total' => $total, 'distribuicao' => $dist];
    }
}
