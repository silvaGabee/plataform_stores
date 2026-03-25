<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email, ?int $storeId = null): ?array
    {
        if ($storeId !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND store_id = ?");
            $stmt->execute([$email, $storeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND store_id IS NULL");
            $stmt->execute([$email]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmailAndStore(string $email, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND store_id = ?");
        $stmt->execute([$email, $storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Retorna todos os usuários com esse e-mail (em qualquer loja) para login na plataforma. */
    public function findAllByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? ORDER BY store_id IS NULL DESC, id ASC");
        $stmt->execute([$email]);
        return $stmt->fetchAll();
    }

    /**
     * IDs das lojas em que o e-mail atua como equipe (gerente/funcionário), não como cliente.
     *
     * @return int[]
     */
    public function findStaffStoreIdsByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT store_id FROM users WHERE email = ? AND store_id IS NOT NULL
             AND user_type IN ('gerente', 'funcionario') ORDER BY store_id"
        );
        $stmt->execute([$email]);
        $ids = [];
        while ($row = $stmt->fetch()) {
            $ids[] = (int) $row['store_id'];
        }
        return $ids;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (store_id, name, email, password, user_type) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'] ?? null,
            $data['name'],
            $data['email'],
            $data['password'],
            $data['user_type'] ?? 'cliente',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'email', 'password', 'user_type'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) return true;
        $params[] = $id;
        $stmt = $this->pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function listByStore(int $storeId, ?string $userType = null): array
    {
        $sql = "SELECT * FROM users WHERE store_id = ?";
        $params = [$storeId];
        if ($userType !== null) {
            $sql .= " AND user_type = ?";
            $params[] = $userType;
        }
        $sql .= " ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listEmployeesByStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE store_id = ? AND user_type IN ('funcionario','gerente') ORDER BY name"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Pedidos em que este utilizador é o cliente (impede DELETE por RESTRICT na BD). */
    public function countOrdersAsCustomer(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Turnos de caixa abertos por este utilizador (impede DELETE por RESTRICT na BD). */
    public function countCashRegistersAsOpener(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cash_registers WHERE opened_by = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
