<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class StockMovementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(array $data): int
    {
        // Quem fez a movimentação. Antes os chamadores passavam null (ou nem
        // passavam) e o histórico não dizia quem vendeu, repôs ou ajustou.
        // Sem user_id explícito, vale quem está logado nesta requisição.
        if (empty($data['user_id'])) {
            $sessao = (int) ($_SESSION['logged_user_id'] ?? 0);
            $data['user_id'] = $sessao > 0 ? $sessao : null;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_movements (store_id, product_id, user_id, type, quantity, reason) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['store_id'],
            $data['product_id'],
            $data['user_id'] ?? null,
            $data['type'],
            $data['quantity'],
            $data['reason'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * LIMIT não aceita parâmetro vinculado: o PDO envia o valor como string e o
     * MySQL recebe LIMIT '50', que é erro de sintaxe. Estes dois métodos
     * respondiam 500 desde sempre. O limite é forçado a inteiro e interpolado —
     * não vem do usuário, e o cast fecha qualquer injeção.
     */
    private static function limite(int $limit, int $padrao): int
    {
        return $limit > 0 ? min($limit, 500) : $padrao;
    }

    /** Filtra pela loja: sem isso, o id de um produto de outra loja abria o histórico dela. */
    public function listByProduct(int $productId, int $storeId, int $limit = 50): array
    {
        $limit = self::limite($limit, 50);
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name, u.name AS user_name FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             LEFT JOIN users u ON u.id = sm.user_id
             WHERE sm.product_id = ? AND sm.store_id = ? ORDER BY sm.created_at DESC, sm.id DESC LIMIT {$limit}"
        );
        $stmt->execute([$productId, $storeId]);
        return $stmt->fetchAll();
    }

    public function listByStore(int $storeId, int $limit = 100): array
    {
        $limit = self::limite($limit, 100);
        $stmt = $this->pdo->prepare(
            "SELECT sm.*, p.name as product_name FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             WHERE sm.store_id = ? ORDER BY sm.created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    /**
     * Histórico do gerente: filtros por produto, tipo, pessoa, período e texto,
     * paginado, com os totais do filtro inteiro (não só da página).
     *
     * @param array{product_id?:int,type?:string,user_id?:int,from?:string,to?:string,q?:string} $f
     * @return array{rows:array,total:int,totais:array}
     */
    public function search(int $storeId, array $f, int $page = 1, int $perPage = 50): array
    {
        $where = ['sm.store_id = ?'];
        $args = [$storeId];
        if (!empty($f['product_id'])) {
            $where[] = 'sm.product_id = ?';
            $args[] = (int) $f['product_id'];
        }
        if (!empty($f['type']) && in_array($f['type'], ['entrada', 'saida', 'ajuste', 'devolucao'], true)) {
            $where[] = 'sm.type = ?';
            $args[] = $f['type'];
        }
        if (!empty($f['user_id'])) {
            $where[] = 'sm.user_id = ?';
            $args[] = (int) $f['user_id'];
        }
        if (!empty($f['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $where[] = 'sm.created_at >= ?';
            $args[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $where[] = 'sm.created_at < ? + INTERVAL 1 DAY';
            $args[] = $f['to'] . ' 00:00:00';
        }
        if (!empty($f['q'])) {
            $where[] = '(p.name LIKE ? OR sm.reason LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $f['q']) . '%';
            $args[] = $like;
            $args[] = $like;
        }
        $w = implode(' AND ', $where);
        $from = "FROM stock_movements sm JOIN products p ON p.id = sm.product_id LEFT JOIN users u ON u.id = sm.user_id WHERE {$w}";

        $st = $this->pdo->prepare("SELECT COUNT(*) AS n,
                COALESCE(SUM(CASE WHEN sm.type IN ('entrada','devolucao') THEN sm.quantity ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN sm.type = 'saida' THEN sm.quantity ELSE 0 END), 0) AS saidas,
                COALESCE(SUM(CASE WHEN sm.type = 'ajuste' THEN sm.quantity ELSE 0 END), 0) AS ajustes
            {$from}");
        $st->execute($args);
        $tot = $st->fetch() ?: ['n' => 0, 'entradas' => 0, 'saidas' => 0, 'ajustes' => 0];

        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare("SELECT sm.id, sm.product_id, sm.type, sm.quantity, sm.reason, sm.created_at,
                p.name AS product_name, sm.user_id, u.name AS user_name,
                (SELECT m.role FROM store_members m WHERE m.user_id = sm.user_id AND m.store_id = sm.store_id LIMIT 1) AS user_role
            {$from} ORDER BY sm.created_at DESC, sm.id DESC LIMIT {$perPage} OFFSET {$offset}");
        $st->execute($args);

        return [
            'rows' => $st->fetchAll(),
            'total' => (int) $tot['n'],
            'totais' => [
                'entradas' => (int) $tot['entradas'],
                'saidas' => (int) $tot['saidas'],
                'ajustes' => (int) $tot['ajustes'],
            ],
        ];
    }

    /** Pessoas que já movimentaram estoque nesta loja (para o filtro). */
    public function usersOfStore(int $storeId): array
    {
        $st = $this->pdo->prepare('SELECT DISTINCT u.id, u.name FROM stock_movements sm JOIN users u ON u.id = sm.user_id WHERE sm.store_id = ? ORDER BY u.name');
        $st->execute([$storeId]);

        return $st->fetchAll();
    }
}
