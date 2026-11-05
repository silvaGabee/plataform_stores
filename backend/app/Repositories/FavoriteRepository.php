<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/** Produtos favoritados pelo cliente. */
class FavoriteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** Liga/desliga o favorito. Devolve o estado final (true = favoritado). */
    public function toggle(int $userId, int $productId): bool
    {
        $del = $this->pdo->prepare('DELETE FROM user_favorites WHERE user_id = ? AND product_id = ?');
        $del->execute([$userId, $productId]);
        if ($del->rowCount() > 0) {
            return false;
        }
        $this->pdo->prepare('INSERT IGNORE INTO user_favorites (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);

        return true;
    }

    /** @return list<int> ids dos produtos favoritados nesta loja */
    public function productIds(int $userId, int $storeId): array
    {
        $st = $this->pdo->prepare(
            'SELECT f.product_id FROM user_favorites f INNER JOIN products p ON p.id = f.product_id
             WHERE f.user_id = ? AND p.store_id = ? ORDER BY f.created_at DESC'
        );
        $st->execute([$userId, $storeId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
