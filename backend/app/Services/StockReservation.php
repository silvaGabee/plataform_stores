<?php

namespace App\Services;

use App\Domain\Product\VariantStock;
use App\Repositories\OrderRepository;

/**
 * Estoque disponível para venda = saldo físico − reservas.
 *
 * Reserva é o que está em pedidos online pendentes ainda dentro do prazo de
 * pagamento (orders.expires_at). O saldo físico só baixa quando o pagamento é
 * confirmado; até lá a peça fica "segurada" para quem está pagando, e nem o
 * PDV nem outro cliente conseguem vendê-la.
 *
 * Antes não havia reserva: o cliente pagava o PIX, o balcão vendia a última
 * peça nesse meio-tempo e o pedido pago ficava travado sem mercadoria.
 */
final class StockReservation
{
    /** @var array<int, array<int, array<string, int>>> */
    private static array $cache = [];

    /** @return array<int, array<string, int>> [product_id][variant_key|''] => qtd */
    public static function map(int $storeId): array
    {
        if (!isset(self::$cache[$storeId])) {
            self::$cache[$storeId] = (new OrderRepository())->reservedQuantities($storeId);
        }

        return self::$cache[$storeId];
    }

    public static function forget(?int $storeId = null): void
    {
        if ($storeId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$storeId]);
        }
    }

    public static function reserved(int $storeId, int $productId, ?string $variantKey): int
    {
        $map = self::map($storeId);

        return (int) ($map[$productId][$variantKey ?? ''] ?? 0);
    }

    /** Quanto ainda pode ser vendido desta variação (ou do produto simples). */
    public static function available(array $product, ?string $variantKey): int
    {
        $fisico = VariantStock::availableForSale($product, $variantKey);
        $storeId = (int) ($product['store_id'] ?? 0);
        if ($storeId < 1) {
            return $fisico;
        }

        return max(0, $fisico - self::reserved($storeId, (int) $product['id'], $variantKey));
    }

    /**
     * Desconta as reservas do produto já hidratado: variações (stock_quantity
     * de cada combinação e da matriz) e o total. Usado pela vitrine e pelo PDV,
     * que mostram "esgotado" por tamanho.
     */
    public static function applyTo(array $product): array
    {
        $storeId = (int) ($product['store_id'] ?? 0);
        $pid = (int) ($product['id'] ?? 0);
        $res = $storeId > 0 ? (self::map($storeId)[$pid] ?? []) : [];
        if ($res === []) {
            return $product;
        }
        $totalReservado = 0;
        foreach ($product['variants'] ?? [] as $i => $v) {
            if (($v['variant_type'] ?? '') !== 'combinacao') {
                continue;
            }
            $r = (int) ($res[(string) $v['variant_value']] ?? 0);
            if ($r > 0) {
                $product['variants'][$i]['stock_quantity'] = max(0, (int) $v['stock_quantity'] - $r);
                $totalReservado += min($r, (int) $v['stock_quantity']);
            }
        }
        if (!empty($product['variants_matrix']['stock']) && is_array($product['variants_matrix']['stock'])) {
            foreach ($product['variants_matrix']['stock'] as $size => $row) {
                foreach ((array) $row as $color => $qty) {
                    $r = (int) ($res[$color . '|' . $size] ?? 0);
                    if ($r > 0) {
                        $product['variants_matrix']['stock'][$size][$color] = max(0, (int) $qty - $r);
                    }
                }
            }
        }
        if ($totalReservado === 0 && isset($res[''])) {
            $totalReservado = (int) $res[''];
        }
        $product['stock_quantity'] = max(0, (int) ($product['stock_quantity'] ?? 0) - $totalReservado);

        return $product;
    }
}
