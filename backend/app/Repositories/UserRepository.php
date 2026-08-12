<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Pessoas. Uma linha por e-mail, sempre.
 *
 * Antes desta camada existir assim, a mesma pessoa tinha uma linha por loja
 * mais uma "de plataforma", cada qual com sua senha — e o login entrava na
 * primeira cuja senha casasse. O vínculo com a loja mudou-se para
 * `store_members`; aqui ficou só a identidade.
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** O e-mail é único, então isto devolve a pessoa — ou null. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim($email)]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            trim((string) $data['email']),
            $data['password'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $campos = [];
        $params = [];
        foreach (['name', 'email', 'password'] as $c) {
            if (array_key_exists($c, $data)) {
                $campos[] = "{$c} = ?";
                $params[] = $c === 'email' ? trim((string) $data[$c]) : $data[$c];
            }
        }
        if ($campos === []) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $campos) . ' WHERE id = ?');

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');

        return $stmt->execute([$id]);
    }

    /** Pedidos em que esta pessoa é a cliente (impede DELETE por RESTRICT na BD). */
    public function countOrdersAsCustomer(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** Turnos de caixa abertos por esta pessoa (impede DELETE por RESTRICT na BD). */
    public function countCashRegistersAsOpener(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cash_registers WHERE opened_by = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
