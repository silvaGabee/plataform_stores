<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class EmployeeRoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** Cargos da pessoa nesta loja — ela pode ter cargos em outras lojas também. */
    public function getRolesByUser(int $userId, int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.* FROM roles r 
             INNER JOIN employee_roles er ON er.role_id = r.id 
             WHERE er.user_id = ? AND r.store_id = ?
             ORDER BY r.name"
        );
        $stmt->execute([$userId, $storeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.name FROM users u 
             INNER JOIN employee_roles er ON er.user_id = u.id 
             WHERE er.role_id = ? ORDER BY u.name"
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Define quais usuários estão neste cargo (substitui os atuais). */
    public function setRoleUsers(int $roleId, array $userIds): void
    {
        $this->pdo->prepare("DELETE FROM employee_roles WHERE role_id = ?")->execute([$roleId]);
        if (empty($userIds)) {
            return;
        }
        $stmt = $this->pdo->prepare("INSERT INTO employee_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $stmt->execute([$uid, $roleId]);
            }
        }
    }

    public function remove(int $userId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM employee_roles WHERE user_id = ? AND role_id = ?");
        return $stmt->execute([$userId, $roleId]);
    }

    /**
     * Define os cargos da pessoa nesta loja (substitui só os desta loja).
     * Antes apagava os cargos dela em todas as lojas.
     */
    public function setUserRoles(int $userId, array $roleIds, int $storeId): void
    {
        $this->pdo->prepare(
            "DELETE FROM employee_roles
              WHERE user_id = ? AND role_id IN (SELECT id FROM roles WHERE store_id = ?)"
        )->execute([$userId, $storeId]);
        $stmt = $this->pdo->prepare("INSERT INTO employee_roles (user_id, role_id) VALUES (?, ?)");
        foreach (array_unique(array_map('intval', $roleIds)) as $rid) {
            if ($rid > 0) {
                $stmt->execute([$userId, $rid]);
            }
        }
    }
}
