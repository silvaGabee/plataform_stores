<?php

namespace App\Domain\Product;

use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;

/**
 * Estoque por variação — o caminho crítico da venda.
 *
 * Decide quanto há disponível de uma combinação e aplica a baixa. Estava em
 * Helpers/functions.php, como funções globais que recebiam repositórios por
 * parâmetro: o coração transacional do sistema morando num arquivo de utilidades
 * e sem como ser testado isoladamente.
 *
 * Dois formatos de variação convivem:
 *   MATRIZ    cor × tamanho, gravada em linhas `combinacao` com "Cor|Tamanho";
 *   SIMPLES   uma linha por valor, com o tipo na coluna `variant_type`.
 * Linhas `_meta` guardam configuração (eixo, cor em hex) e nunca são estoque.
 */
final class VariantStock
{
    /** Prefixo das linhas de configuração, que não contam como estoque. */
    public const META = '_meta';

    /**
     * O produto é vendido por variação?
     *
     * Um produto meio configurado (eixo definido, nenhuma combinação) responde
     * true de propósito: assim a venda é recusada por falta de combinação, em
     * vez de sair contra `products.stock_quantity` sem baixa em variação nenhuma.
     */
    public static function hasVariants(array $product): bool
    {
        if (!empty($product['variants_matrix']) && is_array($product['variants_matrix'])) {
            return !empty($product['variants_matrix']['colors']);
        }
        $matrix = product_variants_rows_to_matrix($product['variants'] ?? []);

        return $matrix !== null || (!empty($product['variants']) && is_array($product['variants']));
    }

    /** Soma o estoque das variações, ignorando as linhas de configuração. */
    public static function totalStock(array $variants): int
    {
        $sum = 0;
        foreach ($variants as $row) {
            if (!is_array($row) || ($row['variant_type'] ?? '') === self::META) {
                continue;
            }
            $sum += max(0, (int) ($row['stock_quantity'] ?? 0));
        }

        return $sum;
    }

    /**
     * Quanto pode ser vendido desta combinação.
     *
     * Produto com variação e sem chave devolve 0 — é o que impede vender "um
     * tênis" sem dizer qual número.
     */
    public static function availableForSale(array $product, ?string $variantKey): int
    {
        if (!self::hasVariants($product)) {
            return max(0, (int) ($product['stock_quantity'] ?? 0));
        }
        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return 0;
        }

        // "Cor|Tamanho" — combinação da matriz.
        if (str_contains($vk, '|') && !str_contains($vk, ':')) {
            $partes = explode('|', $vk, 2);
            if (count($partes) === 2) {
                return product_variant_stock_for_combination($product, $partes[0], $partes[1]);
            }
        }

        // "tipo:valor" — variação simples qualificada.
        if (str_contains($vk, ':')) {
            [$tipo, $valor] = explode(':', $vk, 2);
            foreach ($product['variants'] ?? [] as $row) {
                if (is_array($row)
                    && ($row['variant_type'] ?? '') === $tipo
                    && ($row['variant_value'] ?? '') === $valor) {
                    return max(0, (int) ($row['stock_quantity'] ?? 0));
                }
            }
        }

        // Só o valor — procura em qualquer tipo que não seja configuração.
        foreach ($product['variants'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tipo = (string) ($row['variant_type'] ?? '');
            if ($tipo === '' || $tipo === self::META) {
                continue;
            }
            if ((string) ($row['variant_value'] ?? '') === $vk) {
                return max(0, (int) ($row['stock_quantity'] ?? 0));
            }
        }

        return 0;
    }

    /**
     * Baixa o estoque da venda. Devolve false quando NÃO foi possível — saldo
     * insuficiente ou variação inexistente.
     *
     * Quem chama deve tratar false como falha da venda e desfazer a transação:
     * a decisão de "tem saldo?" e a escrita acontecem na mesma instrução SQL
     * (ver ProductVariantRepository::decrementStock), que é o que impede duas
     * vendas simultâneas de passarem pela mesma última unidade.
     */
    public static function applySaleDecrement(
        array $product,
        int $quantity,
        ?string $variantKey,
        ProductVariantRepository $variantRepo,
        ProductRepository $productRepo
    ): bool {
        $productId = (int) ($product['id'] ?? 0);
        if ($productId < 1 || $quantity < 1) {
            return false;
        }

        if (!self::hasVariants($product)) {
            return $productRepo->decrementStock($productId, $quantity);
        }

        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return false;
        }
        [$tipo, $valor] = self::resolveTypeAndValue($product, $vk);
        if ($tipo === null || $valor === null || $tipo === '' || $valor === '') {
            return false;
        }
        if (!$variantRepo->decrementStock($productId, $tipo, $valor, $quantity)) {
            return false;
        }

        // products.stock_quantity é o total derivado das variações — relido do
        // banco já com a baixa aplicada.
        $productRepo->updateStock($productId, $variantRepo->totalStock($productId));

        return true;
    }

    /**
     * Traduz a chave de variação no par (variant_type, variant_value) gravado.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function resolveTypeAndValue(array $product, string $vk): array
    {
        if (str_contains($vk, '|') && !str_contains($vk, ':')) {
            return ['combinacao', $vk];
        }
        if (str_contains($vk, ':')) {
            [$tipo, $valor] = explode(':', $vk, 2);

            return [trim($tipo), trim($valor)];
        }
        foreach ($product['variants'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tipo = (string) ($row['variant_type'] ?? '');
            if ($tipo === '' || $tipo === self::META) {
                continue;
            }
            if ((string) ($row['variant_value'] ?? '') === $vk) {
                return [$tipo, $vk];
            }
        }

        return [null, null];
    }
}
