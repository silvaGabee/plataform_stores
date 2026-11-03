<?php

namespace App\Domain\Finance;

/**
 * Valores monetários em centavos inteiros.
 *
 * Ponto flutuante não representa 0,10 com exatidão; somar e dividir reais em
 * float acumula erro de arredondamento. Toda divisão de dinheiro deste módulo
 * é feita em centavos (inteiros), e só o resultado volta para reais.
 */
final class Money
{
    public static function toCents(float $reais): int
    {
        return (int) round($reais * 100);
    }

    public static function fromCents(int $centavos): float
    {
        return $centavos / 100;
    }

    /**
     * Divide um total em n partes iguais SEM perder centavos.
     *
     * Arredondar cada parte isoladamente quebra a soma: R$ 1.000,00 / 3 vira
     * 3 × 333,33 = 999,99. Aqui a divisão é inteira em centavos e o resto
     * (no máximo n − 1 centavos) é distribuído, um centavo por vez, às
     * primeiras partes:
     *
     *     base  = ⌊T / n⌋        (em centavos)
     *     resto = T − base × n   (0 ≤ resto < n)
     *     parte_k = base + 1, para k < resto;  base, caso contrário
     *
     * Garantias: Σ partes = T exatamente, e duas partes nunca diferem em mais
     * de um centavo.
     *
     * @return list<float>
     */
    public static function splitEvenly(float $total, int $partes): array
    {
        if ($partes < 1) {
            throw new \InvalidArgumentException('O número de partes deve ser ao menos 1.');
        }
        $centavos = self::toCents($total);
        $sinal = $centavos < 0 ? -1 : 1;
        $centavos = abs($centavos);
        $base = intdiv($centavos, $partes);
        $resto = $centavos - $base * $partes;

        $out = [];
        for ($k = 0; $k < $partes; $k++) {
            $out[] = self::fromCents($sinal * ($base + ($k < $resto ? 1 : 0)));
        }

        return $out;
    }
}
