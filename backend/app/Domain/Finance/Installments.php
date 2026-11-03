<?php

namespace App\Domain\Finance;

/**
 * Parcelamento pelo Sistema Francês de Amortização (tabela Price).
 *
 * Parcelas iguais e periódicas, juros compostos sobre o saldo devedor.
 * A parcela é o valor presente P dividido pelo fator de valor presente de
 * uma série uniforme:
 *
 *     PMT = P · i / (1 − (1 + i)^(−n))
 *
 * Em cada período k:
 *     juros_k        = saldo_(k−1) · i
 *     amortização_k  = PMT − juros_k
 *     saldo_k        = saldo_(k−1) − amortização_k
 *
 * Os juros diminuem e a amortização cresce a cada parcela; o saldo zera na
 * última. Com i = 0 a fórmula se reduz a P / n (divisão em partes iguais,
 * feita em centavos por Money::splitEvenly para não perder centavo).
 *
 * Arredondamento: cada valor é arredondado ao centavo, como numa fatura real.
 * O resíduo acumulado (no máximo alguns centavos) é absorvido pela última
 * parcela, que amortiza exatamente o saldo restante — assim Σ amortizações = P
 * sempre, e a tabela fecha.
 */
final class Installments
{
    public const MAX_PARCELAS = 48;
    public const MAX_TAXA_MENSAL = 20.0; // % a.m.; acima disso é erro de digitação

    /**
     * @param float $principal       valor financiado (R$)
     * @param float $taxaMensal      taxa de juros ao mês, em % (ex.: 2.5)
     * @param int   $parcelas        número de parcelas
     *
     * @return array{
     *   principal: float, taxa_mensal: float, parcelas: int,
     *   parcela: float, total: float, juros_total: float,
     *   coeficiente: float, taxa_anual_equivalente: float,
     *   tabela: list<array{n: int, parcela: float, juros: float, amortizacao: float, saldo: float}>
     * }
     */
    public static function price(float $principal, float $taxaMensal, int $parcelas): array
    {
        if ($principal <= 0) {
            throw new \InvalidArgumentException('O valor financiado deve ser maior que zero.');
        }
        if ($parcelas < 1 || $parcelas > self::MAX_PARCELAS) {
            throw new \InvalidArgumentException('O número de parcelas deve estar entre 1 e ' . self::MAX_PARCELAS . '.');
        }
        if ($taxaMensal < 0 || $taxaMensal > self::MAX_TAXA_MENSAL) {
            throw new \InvalidArgumentException('A taxa mensal deve estar entre 0% e ' . self::MAX_TAXA_MENSAL . '%.');
        }

        $i = $taxaMensal / 100;
        $principalCent = Money::toCents($principal);

        if ($i == 0.0) {
            $valores = Money::splitEvenly($principal, $parcelas);
            $tabela = [];
            $saldo = $principalCent;
            foreach ($valores as $k => $v) {
                $saldo -= Money::toCents($v);
                $tabela[] = ['n' => $k + 1, 'parcela' => $v, 'juros' => 0.0, 'amortizacao' => $v, 'saldo' => Money::fromCents($saldo)];
            }

            return self::resultado($principal, $taxaMensal, $parcelas, $valores[0], 1 / $parcelas, $tabela);
        }

        // Fator de recuperação de capital: i / (1 − (1+i)^−n).
        $coef = $i / (1 - (1 + $i) ** (-$parcelas));
        $pmtCent = (int) round($principalCent * $coef);

        $tabela = [];
        $saldo = $principalCent;
        for ($k = 1; $k <= $parcelas; $k++) {
            $jurosCent = (int) round($saldo * $i);
            $ultima = $k === $parcelas;
            // Última parcela: amortiza exatamente o que resta.
            $amortCent = $ultima ? $saldo : $pmtCent - $jurosCent;
            $parcelaCent = $ultima ? $saldo + $jurosCent : $pmtCent;
            $saldo -= $amortCent;
            $tabela[] = [
                'n' => $k,
                'parcela' => Money::fromCents($parcelaCent),
                'juros' => Money::fromCents($jurosCent),
                'amortizacao' => Money::fromCents($amortCent),
                'saldo' => Money::fromCents($saldo),
            ];
        }

        return self::resultado($principal, $taxaMensal, $parcelas, Money::fromCents($pmtCent), $coef, $tabela);
    }

    /**
     * Resumo de 1x até $max parcelas, para o operador comparar.
     *
     * @return list<array{parcelas: int, parcela: float, total: float, juros_total: float}>
     */
    public static function options(float $principal, float $taxaMensal, int $max = 12): array
    {
        $max = max(1, min(self::MAX_PARCELAS, $max));
        $out = [];
        for ($n = 1; $n <= $max; $n++) {
            $r = self::price($principal, $taxaMensal, $n);
            $out[] = [
                'parcelas' => $n,
                'parcela' => $r['parcela'],
                'total' => $r['total'],
                'juros_total' => $r['juros_total'],
            ];
        }

        return $out;
    }

    /** @param list<array{parcela: float}> $tabela */
    private static function resultado(float $principal, float $taxaMensal, int $parcelas, float $parcela, float $coef, array $tabela): array
    {
        $totalCent = 0;
        foreach ($tabela as $linha) {
            $totalCent += Money::toCents($linha['parcela']);
        }
        $i = $taxaMensal / 100;

        return [
            'principal' => round($principal, 2),
            'taxa_mensal' => $taxaMensal,
            'parcelas' => $parcelas,
            'parcela' => $parcela,
            'total' => Money::fromCents($totalCent),
            'juros_total' => Money::fromCents($totalCent - Money::toCents($principal)),
            'coeficiente' => round($coef, 8),
            // Taxa equivalente composta: (1 + i)^12 − 1, em %.
            'taxa_anual_equivalente' => round(((1 + $i) ** 12 - 1) * 100, 4),
            'tabela' => $tabela,
        ];
    }
}
