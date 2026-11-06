<?php

namespace App\Domain\Finance;

/**
 * Indicadores por produto: margem, markup, Curva ABC, giro e cobertura.
 *
 * Quando a fórmula não tem resposta (divisor zero), o resultado é null, e a tela
 * mostra a falta do dado em vez de um número inventado.
 */
final class ProductMetrics
{
    /** Limites da Curva ABC, em % acumulado do faturamento. */
    public const LIMITE_A = 80.0;
    public const LIMITE_B = 95.0;

    /** Margem sobre a venda, em %: lucro / receita. */
    public static function marginPercent(float $receita, float $lucro): ?float
    {
        return $receita > 0 ? round($lucro / $receita * 100, 2) : null;
    }

    /** Markup sobre o custo, em %: lucro / custo. Sem custo registrado não há markup. */
    public static function markupPercent(float $custo, float $lucro): ?float
    {
        return $custo > 0 ? round($lucro / $custo * 100, 2) : null;
    }

    /**
     * Curva ABC: ordena pela receita e classifica pelo % acumulado ANTES do item.
     *
     * Olhar o acumulado antes garante que o item que cruza os 80% ainda seja A.
     * Sem isso, um único produto com 90% da receita ficaria fora da classe A.
     * Produto sem receita no período fica fora da curva.
     *
     * @param list<array<string, mixed>> $itens com a chave 'receita'
     * @return list<array<string, mixed>> itens com participacao, acumulado e classe
     */
    public static function abc(array $itens): array
    {
        $itens = array_values(array_filter($itens, static fn (array $i): bool => (float) $i['receita'] > 0));
        usort($itens, static fn (array $a, array $b): int => (float) $b['receita'] <=> (float) $a['receita']);
        $total = array_sum(array_map(static fn (array $i): float => (float) $i['receita'], $itens));

        $acumulado = 0.0;
        foreach ($itens as &$item) {
            $classe = $acumulado < self::LIMITE_A ? 'A' : ($acumulado < self::LIMITE_B ? 'B' : 'C');
            $participacao = (float) $item['receita'] / $total * 100;
            $acumulado += $participacao;
            $item['participacao'] = round($participacao, 2);
            $item['acumulado'] = round($acumulado, 2);
            $item['classe'] = $classe;
        }
        unset($item);

        return $itens;
    }

    /** Giro: quantas vezes o valor do estoque foi vendido (CMV / estoque a custo). */
    public static function turnover(float $cmv, float $estoqueACusto): ?float
    {
        return $estoqueACusto > 0 ? round($cmv / $estoqueACusto, 2) : null;
    }

    /**
     * Dias até o estoque cair de $estoque para $minimo no ritmo de venda da janela.
     *
     * Com mínimo 0 é a cobertura: quantos dias o estoque dura. Sem venda na janela
     * não há ritmo para projetar, e o resultado é null.
     */
    public static function daysUntil(float $estoque, float $minimo, float $vendidoNaJanela, int $diasDaJanela): ?int
    {
        if ($estoque <= $minimo) {
            return 0;
        }
        if ($vendidoNaJanela <= 0 || $diasDaJanela <= 0) {
            return null;
        }

        return (int) floor(($estoque - $minimo) / ($vendidoNaJanela / $diasDaJanela));
    }
}
