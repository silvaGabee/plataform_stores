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

    /**
     * Grava as variações do produto atualizando as linhas no lugar.
     *
     * Antes apagava todas e inseria de novo: uma venda confirmada entre o
     * DELETE e o INSERT não achava a variação, e um erro no meio deixava o
     * produto sem variações. Agora a linha que continua é atualizada (mesmo
     * id, mesmo código de barras), a nova é inserida e a que saiu é apagada.
     *
     * A comparação ignora maiúsculas e acentos, como a chave única do banco
     * (collation utf8mb4_unicode_ci): "azul" → "Azul" é renomear, e "Azul" e
     * "azul" na mesma grade são recusados antes de gravar qualquer coisa.
     */
    public function replaceForProduct(int $productId, array $variants): void
    {
        $novas = [];
        foreach ($variants as $row) {
            $type = (string) ($row['variant_type'] ?? '');
            $value = trim((string) ($row['variant_value'] ?? ''));
            if ($type === '' || $value === '') {
                continue;
            }
            $chave = $type . "\0" . self::chaveSemCaixaNemAcento($value);
            if (isset($novas[$chave])) {
                throw new \InvalidArgumentException(
                    'A grade tem nomes repetidos: "' . str_replace('|', ' / ', $novas[$chave]['value']) . '" e "'
                    . str_replace('|', ' / ', $value) . '" diferem só por maiúscula ou acento. Use um nome só para cada cor e tamanho.'
                );
            }
            $novas[$chave] = ['type' => $type, 'value' => $value, 'stock' => max(0, (int) ($row['stock_quantity'] ?? 0))];
        }

        $st = $this->pdo->prepare('SELECT id, variant_type, variant_value FROM product_variants WHERE product_id = ?');
        $st->execute([$productId]);
        $existentes = [];
        foreach ($st->fetchAll() as $linha) {
            $existentes[$linha['variant_type'] . "\0" . self::chaveSemCaixaNemAcento((string) $linha['variant_value'])] = (int) $linha['id'];
        }

        $del = $this->pdo->prepare('DELETE FROM product_variants WHERE id = ?');
        foreach ($existentes as $chave => $id) {
            if (!isset($novas[$chave])) {
                $del->execute([$id]);
            }
        }
        $up = $this->pdo->prepare('UPDATE product_variants SET variant_value = ?, stock_quantity = ?, sort_order = ? WHERE id = ?');
        $ins = $this->pdo->prepare(
            'INSERT INTO product_variants (product_id, variant_type, variant_value, stock_quantity, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $order = 0;
        try {
            foreach ($novas as $chave => $n) {
                if (isset($existentes[$chave])) {
                    $up->execute([$n['value'], $n['stock'], $order++, $existentes[$chave]]);
                } else {
                    $ins->execute([$productId, $n['type'], $n['value'], $n['stock'], $order++]);
                }
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                // Alguma equivalência da collation que a chave acima não cobre.
                throw new \InvalidArgumentException('A grade tem cores ou tamanhos repetidos (diferem só por maiúscula ou acento). Use um nome só para cada.');
            }
            throw $e;
        }
    }

    /** "Açaí|M" → "acai|m": aproximação da comparação da collation do banco. */
    private static function chaveSemCaixaNemAcento(string $valor): string
    {
        return strtr(mb_strtolower(trim($valor), 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    /**
     * Grava os códigos por variação. A chave é o valor gravado: "Cor|Tamanho" na
     * matriz. Variação que não existe é ignorada.
     *
     * @param array<string, ?string> $codigos
     */
    public function setBarcodes(int $productId, array $codigos): void
    {
        $st = $this->pdo->prepare("UPDATE product_variants SET barcode = ? WHERE product_id = ? AND variant_value = ? AND variant_type <> '_meta'");
        foreach ($codigos as $valor => $codigo) {
            $st->execute([$codigo, $productId, (string) $valor]);
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
