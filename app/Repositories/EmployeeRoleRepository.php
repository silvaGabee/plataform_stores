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

    public function getRolesByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.* FROM roles r 
             INNER JOIN employee_roles er ON er.role_id = r.id 
             WHERE er.user_id = ?"
        );
        $stmt->execute([$userId]);
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

    public function assign(int $userId, int $roleId): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO employee_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$userId, $roleId]);
    }

    public function remove(int $userId, int $roleId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM employee_roles WHERE user_id = ? AND role_id = ?");
        return $stmt->execute([$userId, $roleId]);
    }

    public function setUserRoles(int $userId, array $roleIds): void
    {
        $this->pdo->prepare("DELETE FROM employee_roles WHERE user_id = ?")->execute([$userId]);
        $stmt = $this->pdo->prepare("INSERT INTO employee_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            $stmt->execute([$userId, $rid]);
        }
    }
}
