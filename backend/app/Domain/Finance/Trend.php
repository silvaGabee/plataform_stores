<?php

namespace App\Domain\Finance;

/**
 * Tendência de uma série temporal por regressão linear simples
 * (método dos mínimos quadrados).
 *
 * Os períodos são numerados x = 0, 1, …, n − 1 e o valor de cada um é y.
 * Procura-se a reta ŷ = a + b·x que minimiza Σ (y − ŷ)². A solução fechada é
 *
 *     b = Σ (x − x̄)(y − ȳ) / Σ (x − x̄)²
 *     a = ȳ − b·x̄
 *
 * e a qualidade do ajuste é o coeficiente de determinação
 *
 *     R² = 1 − Σ (y − ŷ)² / Σ (y − ȳ)²
 *
 * (a fração da variação de y que a reta explica; 1 é ajuste perfeito, 0 é
 * reta que não explica nada além da média).
 *
 * Os períodos sem venda entram como zero: são dados, não ausência de dado.
 */
final class Trend
{
    /** Abaixo disso uma reta não diz nada sobre tendência. */
    public const MIN_PONTOS = 3;

    /**
     * @param list<float|int> $y
     * @return array{a: float, b: float, r2: float, n: int}|null
     *         null quando há menos de MIN_PONTOS valores.
     */
    public static function linearRegression(array $y): ?array
    {
        $y = array_values(array_map('floatval', $y));
        $n = count($y);
        if ($n < self::MIN_PONTOS) {
            return null;
        }
        $xMedio = ($n - 1) / 2;          // média de 0..n−1
        $yMedio = array_sum($y) / $n;

        $sxy = 0.0;
        $sxx = 0.0;
        foreach ($y as $x => $yi) {
            $dx = $x - $xMedio;
            $sxy += $dx * ($yi - $yMedio);
            $sxx += $dx * $dx;
        }
        $b = $sxx > 0 ? $sxy / $sxx : 0.0;
        $a = $yMedio - $b * $xMedio;

        $sqRes = 0.0;
        $sqTot = 0.0;
        foreach ($y as $x => $yi) {
            $sqRes += ($yi - ($a + $b * $x)) ** 2;
            $sqTot += ($yi - $yMedio) ** 2;
        }
        // Série constante: a reta horizontal a reproduz exatamente.
        $r2 = $sqTot > 0 ? 1 - $sqRes / $sqTot : 1.0;

        return ['a' => $a, 'b' => $b, 'r2' => $r2, 'n' => $n];
    }

    /**
     * Projeta os próximos `$horizonte` períodos pela reta ajustada.
     *
     * Faturamento não é negativo: uma reta de queda que cruze o zero é
     * truncada em zero — a projeção diz "tende a parar de vender", não
     * "a loja vai pagar para vender".
     *
     * @param list<float|int> $y
     * @return list<float> valores de x = n … n + horizonte − 1, ou [] se não
     *         houver pontos suficientes.
     */
    public static function forecast(array $y, int $horizonte): array
    {
        $reg = self::linearRegression($y);
        if ($reg === null || $horizonte < 1) {
            return [];
        }
        $out = [];
        for ($k = 0; $k < $horizonte; $k++) {
            $x = $reg['n'] + $k;
            $out[] = round(max(0.0, $reg['a'] + $reg['b'] * $x), 2);
        }

        return $out;
    }
}
