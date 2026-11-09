<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Avaliações de produto (1 a 5 estrelas).
 *
 * Só avalia quem comprou: precisa existir um pedido pago do
 * cliente, nesta loja, com o produto. Cada cliente tem uma avaliação por
 * produto — avaliar de novo substitui a anterior.
 *
 * O gerente pode responder (a resposta aparece na vitrine embaixo da
 * avaliação) e remover. Remover só oculta (hidden_at): a avaliação sai da
 * vitrine e da média, e pode ser restaurada. Avaliar de novo não a faz
 * reaparecer — o save() não mexe em hidden_at.
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
             WHERE o.store_id = ? AND o.customer_id = ? AND oi.product_id = ? AND o.status = 'pago'
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
     * @return list<array{rating: int, comment: ?string, created_at: string, autor: string, reply: ?string, replied_at: ?string}>
     */
    public function listByProduct(int $productId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT r.rating, r.comment, r.reply, r.replied_at, r.updated_at AS created_at, u.name
             FROM product_reviews r INNER JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND r.hidden_at IS NULL
             ORDER BY r.updated_at DESC, r.id DESC LIMIT {$limit}"
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
                'reply' => $row['reply'] !== null && $row['reply'] !== '' ? (string) $row['reply'] : null,
                'replied_at' => $row['replied_at'] !== null ? (string) $row['replied_at'] : null,
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
        $st = $this->pdo->prepare('SELECT product_id, AVG(rating) AS media, COUNT(*) AS total FROM product_reviews WHERE store_id = ? AND hidden_at IS NULL GROUP BY product_id');
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
        $st = $this->pdo->prepare('SELECT rating, COUNT(*) AS n FROM product_reviews WHERE product_id = ? AND hidden_at IS NULL GROUP BY rating');
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

    // ------------------------------------------------------------ moderação

    /**
     * Avaliações da loja para o painel, mais recentes primeiro. Aqui vai o
     * nome inteiro e o e-mail: o gerente precisa saber quem escreveu.
     *
     * @param string $filtro 'todas' | 'sem_resposta' | 'removidas' | 'negativas'
     */
    public function listForStore(int $storeId, string $filtro = 'todas', int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $onde = match ($filtro) {
            'sem_resposta' => ' AND r.hidden_at IS NULL AND r.reply IS NULL',
            'removidas' => ' AND r.hidden_at IS NOT NULL',
            'negativas' => ' AND r.hidden_at IS NULL AND r.rating <= 2',
            default => '',
        };
        $st = $this->pdo->prepare(
            "SELECT r.id, r.product_id, p.name AS product_name, r.rating, r.comment, r.reply, r.replied_at,
                    r.hidden_at, r.updated_at, u.name AS customer_name, u.email AS customer_email
             FROM product_reviews r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN products p ON p.id = r.product_id
             WHERE r.store_id = ?{$onde}
             ORDER BY r.updated_at DESC, r.id DESC LIMIT {$limit}"
        );
        $st->execute([$storeId]);

        return $st->fetchAll();
    }

    /** Contagens para os filtros do painel. @return array{todas: int, sem_resposta: int, removidas: int, negativas: int} */
    public function countsForStore(int $storeId): array
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS todas,
                    COALESCE(SUM(hidden_at IS NULL AND reply IS NULL), 0) AS sem_resposta,
                    COALESCE(SUM(hidden_at IS NOT NULL), 0) AS removidas,
                    COALESCE(SUM(hidden_at IS NULL AND rating <= 2), 0) AS negativas
             FROM product_reviews WHERE store_id = ?'
        );
        $st->execute([$storeId]);
        $r = $st->fetch() ?: [];

        return [
            'todas' => (int) ($r['todas'] ?? 0),
            'sem_resposta' => (int) ($r['sem_resposta'] ?? 0),
            'removidas' => (int) ($r['removidas'] ?? 0),
            'negativas' => (int) ($r['negativas'] ?? 0),
        ];
    }

    /** Grava (ou apaga, com null) a resposta da loja. false se a avaliação não é desta loja. */
    public function reply(int $id, int $storeId, ?string $texto, int $userId): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE product_reviews
             SET reply = ?, replied_at = IF(? IS NULL, NULL, NOW()), replied_by = IF(? IS NULL, NULL, ?),
                 updated_at = updated_at
             WHERE id = ? AND store_id = ?'
        );
        $st->execute([$texto, $texto, $texto, $userId, $id, $storeId]);

        return $st->rowCount() > 0 || $this->exists($id, $storeId);
    }

    /** Remove da vitrine (true) ou restaura (false). false se a avaliação não é desta loja. */
    public function setHidden(int $id, int $storeId, bool $ocultar, int $userId): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE product_reviews
             SET hidden_at = IF(?, NOW(), NULL), hidden_by = IF(?, ?, NULL), updated_at = updated_at
             WHERE id = ? AND store_id = ?'
        );
        $st->execute([(int) $ocultar, (int) $ocultar, $userId, $id, $storeId]);

        return $st->rowCount() > 0 || $this->exists($id, $storeId);
    }

    private function exists(int $id, int $storeId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM product_reviews WHERE id = ? AND store_id = ?');
        $st->execute([$id, $storeId]);

        return (bool) $st->fetchColumn();
    }
}
