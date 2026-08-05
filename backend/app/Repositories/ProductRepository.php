<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdAndStore(int $id, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $storeId]);
        return $stmt->fetch() ?: null;
    }

    public function listByStore(int $storeId, bool $onlyWithStock = false): array
    {
        $sql = "SELECT * FROM products WHERE store_id = ?";
        if ($onlyWithStock) {
            $sql .= ' AND stock_quantity > 0';
        }
        $sql .= ' ORDER BY name';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$storeId]) ? $stmt->fetchAll() : [];
    }

    public function listByStoreAndCategory(int $storeId, int $categoryId, bool $onlyWithStock = false): array
    {
        $sql = 'SELECT DISTINCT p.* FROM products p
            INNER JOIN product_vitrine_categories pvc ON pvc.product_id = p.id
            WHERE p.store_id = ? AND pvc.vitrine_category_id = ?';
        if ($onlyWithStock) {
            $sql .= ' AND p.stock_quantity > 0';
        }
        $sql .= ' ORDER BY p.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId, $categoryId]);

        return $stmt->fetchAll() ?: [];
    }

    public function updateVitrineCategoryId(int $id, ?int $categoryId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE products SET vitrine_category_id = ? WHERE id = ?');

        return $stmt->execute([
            $categoryId !== null && $categoryId > 0 ? $categoryId : null,
            $id,
        ]);
    }

    public function listLowStock(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM products WHERE store_id = ? AND min_stock > 0 AND stock_quantity <= min_stock ORDER BY stock_quantity ASC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (store_id, vitrine_category_id, name, description, cost_price, sale_price, stock_quantity, min_stock)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $categoryId = isset($data['vitrine_category_id']) && $data['vitrine_category_id'] !== ''
            ? (int) $data['vitrine_category_id']
            : null;
        $stmt->execute([
            $data['store_id'],
            $categoryId > 0 ? $categoryId : null,
            $data['name'],
            $data['description'] ?? null,
            $data['cost_price'] ?? 0,
            $data['sale_price'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['min_stock'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $categoryId = array_key_exists('vitrine_category_id', $data)
            ? ($data['vitrine_category_id'] !== '' && $data['vitrine_category_id'] !== null
                ? (int) $data['vitrine_category_id']
                : null)
            : null;
        if (array_key_exists('vitrine_category_id', $data)) {
            $stmt = $this->pdo->prepare(
                'UPDATE products SET name = ?, description = ?, cost_price = ?, sale_price = ?, stock_quantity = ?, min_stock = ?, vitrine_category_id = ? WHERE id = ?'
            );

            return $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['cost_price'] ?? 0,
                $data['sale_price'] ?? 0,
                $data['stock_quantity'] ?? 0,
                $data['min_stock'] ?? 0,
                $categoryId > 0 ? $categoryId : null,
                $id,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products SET name = ?, description = ?, cost_price = ?, sale_price = ?, stock_quantity = ?, min_stock = ? WHERE id = ?'
        );

        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['cost_price'] ?? 0,
            $data['sale_price'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['min_stock'] ?? 0,
            $id,
        ]);
    }

    /**
     * Grava um valor absoluto de estoque.
     *
     * Use apenas para ajuste manual do painel ou para recalcular o total a
     * partir das variações. Para venda, use decrementStock(): esta função lê e
     * escreve em momentos diferentes, e duas vendas simultâneas se sobrescrevem.
     */
    public function updateStock(int $id, int $quantity): bool
    {
        $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        return $stmt->execute([max(0, $quantity), $id]);
    }

    /**
     * Baixa estoque do produto sem variações. Devolve false se faltava saldo.
     *
     * Decisão e escrita na mesma instrução: o banco só atualiza a linha se ela
     * ainda tiver o saldo, então duas requisições concorrentes disputando as
     * mesmas unidades resultam em uma vitoriosa e uma recusada — nunca duas.
     */
    public function decrementStock(int $id, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE products SET stock_quantity = stock_quantity - ?
              WHERE id = ? AND stock_quantity >= ?'
        );
        $stmt->execute([$quantity, $id, $quantity]);

        return $stmt->rowCount() === 1;
    }

    /** Devolve estoque ao produto (estorno). Sem guarda: repor não pode falhar. */
    public function incrementStock(int $id, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
        $stmt->execute([$quantity, $id]);

        return $stmt->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
