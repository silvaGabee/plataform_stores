<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductVariantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function listByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_variants WHERE product_id = ? ORDER BY variant_type ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$productId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Variações de vários produtos numa consulta, agrupadas por product_id.
     * Ver ProductImageRepository::getByProductIds — mesmo motivo.
     *
     * @param int[] $productIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listByProducts(array $productIds): array
    {
        $ids = ProductImageRepository::idsValidos($productIds);
        if ($ids === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM product_variants WHERE product_id IN ({$marcadores})
              ORDER BY product_id ASC, variant_type ASC, sort_order ASC, id ASC"
        );
        $stmt->execute($ids);

        $porProduto = [];
        foreach ($stmt->fetchAll() ?: [] as $linha) {
            $porProduto[(int) $linha['product_id']][] = $linha;
        }

        return $porProduto;
    }

    public function replaceForProduct(int $productId, array $variants): void
    {
        $del = $this->pdo->prepare('DELETE FROM product_variants WHERE product_id = ?');
        $del->execute([$productId]);

        if ($variants === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO product_variants (product_id, variant_type, variant_value, stock_quantity, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $order = 0;
        foreach ($variants as $row) {
            $type = (string) ($row['variant_type'] ?? '');
            $value = trim((string) ($row['variant_value'] ?? ''));
            $stock = max(0, (int) ($row['stock_quantity'] ?? 0));
            if ($type === '' || $value === '') {
                continue;
            }
            $stmt->execute([$productId, $type, $value, $stock, $order++]);
        }
    }

    public function totalStock(int $productId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants WHERE product_id = ?');
        $stmt->execute([$productId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Baixa estoque da variação. Devolve false se não havia saldo suficiente.
     *
     * A condição `stock_quantity >= ?` faz o próprio banco decidir na mesma
     * instrução que escreve — sem ler antes e gravar depois, então duas vendas
     * simultâneas não conseguem passar pelo mesmo saldo.
     *
     * Antes daqui saía `GREATEST(0, stock - ?)`, que aceitava vender mais do
     * que existia: com estoque 3 e venda de 5, o saldo ia a 0 e a operação era
     * reportada como bem-sucedida. Duas unidades saíam sem existir.
     */
    public function decrementStock(int $productId, string $variantType, string $variantValue, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE product_variants
             SET stock_quantity = stock_quantity - ?
             WHERE product_id = ? AND variant_type = ? AND variant_value = ? AND stock_quantity >= ?'
        );
        $stmt->execute([$quantity, $productId, $variantType, $variantValue, $quantity]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Devolve estoque à variação (estorno / rollback lógico).
     * Sem guarda de saldo: repor nunca pode falhar por falta.
     */
    public function incrementStock(int $productId, string $variantType, string $variantValue, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE product_variants
             SET stock_quantity = stock_quantity + ?
             WHERE product_id = ? AND variant_type = ? AND variant_value = ?'
        );
        $stmt->execute([$quantity, $productId, $variantType, $variantValue]);

        return $stmt->rowCount() === 1;
    }
}
