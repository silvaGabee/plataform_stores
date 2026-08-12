<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Vínculo entre pessoa e loja.
 *
 * Substitui o par users.store_id + users.user_type, que obrigava a criar uma
 * conta nova (com senha própria) para cada loja onde a pessoa trabalhasse.
 */
class StoreMemberRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** Cargo da pessoa nesta loja, ou null se não trabalha nela. */
    public function role(int $userId, int $storeId): ?string
    {
        if ($userId < 1 || $storeId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT role FROM store_members WHERE user_id = ? AND store_id = ?');
        $stmt->execute([$userId, $storeId]);
        $role = $stmt->fetchColumn();

        return $role === false ? null : (string) $role;
    }

    /** Cria ou atualiza o vínculo. */
    public function upsert(int $userId, int $storeId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO store_members (user_id, store_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );
        $stmt->execute([$userId, $storeId, $role]);
    }

    public function remove(int $userId, int $storeId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM store_members WHERE user_id = ? AND store_id = ?');
        $stmt->execute([$userId, $storeId]);

        return $stmt->rowCount() > 0;
    }

    /** Ids das lojas em que a pessoa trabalha. @return int[] */
    public function storeIdsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT store_id FROM store_members WHERE user_id = ? ORDER BY store_id');
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Equipe da loja, já com os dados da pessoa.
     * O cargo vem como `user_type` para as telas do painel, que sempre
     * chamaram esse campo assim.
     */
    public function listMembers(int $storeId, ?string $role = null): array
    {
        $sql = 'SELECT u.id, u.name, u.email, u.created_at, m.role AS user_type, m.store_id
                  FROM store_members m JOIN users u ON u.id = m.user_id
                 WHERE m.store_id = ?';
        $params = [$storeId];
        if ($role !== null) {
            $sql .= ' AND m.role = ?';
            $params[] = $role;
        }
        $sql .= ' ORDER BY u.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Quantos gerentes a loja tem — impede remover o último. */
    public function countByRole(int $storeId, string $role): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM store_members WHERE store_id = ? AND role = ?');
        $stmt->execute([$storeId, $role]);

        return (int) $stmt->fetchColumn();
    }

    /** Desfaz todos os vínculos da loja (usado ao excluí-la). */
    public function removeAllForStore(int $storeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM store_members WHERE store_id = ?');
        $stmt->execute([$storeId]);
    }
}
