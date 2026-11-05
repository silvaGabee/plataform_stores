<?php

namespace App\Services;

/**
 * Conta do pedido online, num lugar só, para o checkout, o pagamento e a
 * vitrine mostrarem o mesmo número:
 *
 *     total = itens − cupom − desconto_pix + frete
 *
 * O desconto do PIX incide sobre os itens já com o cupom, nunca sobre o frete.
 * orders.discount guarda cupom + PIX (é o que o BI rateia no lucro) e
 * orders.pix_discount guarda só a parte do PIX.
 */
final class OrderPricing
{
    /** Teto do desconto à vista: acima disso é quase certamente erro de digitação. */
    public const PIX_MAX_PCT = 30.0;

    public static function pixPct(array $store): float
    {
        return max(0.0, min(self::PIX_MAX_PCT, round((float) ($store['pix_discount_pct'] ?? 0), 2)));
    }

    /** Valor com o desconto do PIX, ou null quando a loja não oferece desconto. */
    public static function pixPrice(array $store, float $amount): ?float
    {
        $pct = self::pixPct($store);
        if ($pct <= 0 || $amount <= 0) {
            return null;
        }

        return round($amount - self::pixDiscount($amount, $pct), 2);
    }

    /**
     * Calculado em centavos inteiros: o checkout.js faz a mesma conta assim, e
     * em ponto flutuante 39,90 × 5% = 1,99499… arredondava diferente em PHP e
     * em JavaScript — a tela mostraria um centavo a menos do que o cobrado.
     */
    public static function pixDiscount(float $base, float $pct): float
    {
        if ($base <= 0 || $pct <= 0) {
            return 0.0;
        }
        $centavos = (int) round($base * 100);

        return round($centavos * $pct / 100) / 100;
    }

    /**
     * @return array{itens: float, cupom: float, pix_discount: float, frete: float, discount: float, total: float}
     */
    public static function breakdown(float $itens, float $cupom, float $frete, float $pixPct, bool $pagaNoPix): array
    {
        $itens = round($itens, 2);
        $cupom = round(min(max(0.0, $cupom), $itens), 2);
        $frete = round(max(0.0, $frete), 2);
        $pix = $pagaNoPix ? self::pixDiscount($itens - $cupom, $pixPct) : 0.0;

        return [
            'itens' => $itens,
            'cupom' => $cupom,
            'pix_discount' => $pix,
            'frete' => $frete,
            'discount' => round($cupom + $pix, 2),
            'total' => round($itens - $cupom - $pix + $frete, 2),
        ];
    }
}
