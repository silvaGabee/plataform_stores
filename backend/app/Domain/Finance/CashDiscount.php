<?php

namespace App\Domain\Finance;

/**
 * Desconto à vista (PIX) visto como taxa de juros.
 *
 * Quem paga à vista recebe d% de desconto; quem parcela em n vezes "sem juros"
 * paga o preço cheio P em n parcelas mensais de P/n, a primeira daqui a um mês.
 * Para a loja, dar o desconto equivale a receber hoje P·(1 − d) em vez desse
 * fluxo. A taxa que iguala os dois valores presentes é a taxa embutida no
 * "sem juros":
 *
 *     P · (1 − d) = (P / n) · a(n, i),   com  a(n, i) = (1 − (1 + i)^(−n)) / i
 *
 *     ⇒  1 − d = a(n, i) / n
 *
 * Não há fórmula fechada para i; a equação é resolvida por bisseção (a(n, i)/n
 * decresce com i, então há uma única raiz). Para n = 1 a resposta é exata:
 * i = d / (1 − d).
 *
 * Leitura: se i sai maior que a taxa que a loja cobra no parcelamento com
 * juros, o desconto do PIX está "caro" — a loja abre mão de mais do que
 * ganharia financiando o cliente.
 */
final class CashDiscount
{
    /**
     * Taxa mensal (em %) equivalente a um desconto à vista.
     *
     * @param float $descontoPct  desconto à vista, em % (ex.: 5)
     * @param int   $parcelas     parcelas sem juros oferecidas no cartão
     */
    public static function taxaEquivalente(float $descontoPct, int $parcelas): float
    {
        if ($descontoPct < 0 || $descontoPct >= 100) {
            throw new \InvalidArgumentException('O desconto deve estar entre 0% e 100%.');
        }
        if ($parcelas < 1) {
            throw new \InvalidArgumentException('O número de parcelas deve ser ao menos 1.');
        }
        $d = $descontoPct / 100;
        if ($d == 0.0) {
            return 0.0;
        }
        if ($parcelas === 1) {
            return round($d / (1 - $d) * 100, 4);
        }

        $alvo = 1 - $d;
        $baixo = 0.0;
        $alto = 1.0; // 100% a.m.: muito acima de qualquer caso real
        for ($k = 0; $k < 200; $k++) {
            $meio = ($baixo + $alto) / 2;
            if (self::fatorMedio($meio, $parcelas) > $alvo) {
                $baixo = $meio; // ainda vale mais que o à vista: taxa precisa subir
            } else {
                $alto = $meio;
            }
        }

        return round(($baixo + $alto) / 2 * 100, 4);
    }

    /** a(n, i) / n — valor presente de R$ 1 dividido em n parcelas mensais. */
    public static function fatorMedio(float $taxa, int $parcelas): float
    {
        if ($taxa == 0.0) {
            return 1.0;
        }

        return (1 - (1 + $taxa) ** (-$parcelas)) / $taxa / $parcelas;
    }
}
