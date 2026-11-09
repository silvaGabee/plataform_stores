<?php

namespace App\Domain\Goals;

/**
 * As contas da tela de Metas que não dependem de banco.
 *
 * Ficavam dentro do GoalsApiController, misturadas com as consultas, e por
 * isso não tinham teste. O controller busca os números; daqui sai o que a
 * tela mostra.
 */
final class TeamGoal
{
    /**
     * De onde veio o vendido no mês.
     *
     * Funcionário é quem está na equipe hoje; qualquer outra pessoa com vendas
     * no PDV (o gerente) conta como gerente. A vitrine e as vendas de quem saiu
     * da equipe não têm vendedor, então ficam com o que sobra — por isso as
     * três partes sempre somam o total da loja.
     *
     * @param array<int, float> $vendasPorPessoa id do vendedor => total vendido
     * @param list<int>         $idsFuncionarios
     * @return array{employees: float, managers: float, others: float}
     */
    public static function salesBySource(array $vendasPorPessoa, array $idsFuncionarios, float $totalLoja): array
    {
        // Em centavos: somar reais em float deixaria "outras" em -0,00 ou 0,01.
        $equipe = 0;
        $gerentes = 0;
        foreach ($vendasPorPessoa as $id => $total) {
            $centavos = (int) round((float) $total * 100);
            if (in_array((int) $id, $idsFuncionarios, true)) {
                $equipe += $centavos;
            } else {
                $gerentes += $centavos;
            }
        }
        $loja = (int) round($totalLoja * 100);

        return [
            'employees' => $equipe / 100,
            'managers' => $gerentes / 100,
            'others' => ($loja - $equipe - $gerentes) / 100,
        ];
    }

    /**
     * Os N meses completos antes do período da meta: [primeiro dia, último dia].
     *
     * O mês da meta fica de fora porque ainda está em andamento.
     *
     * @return array{0: string, 1: string}
     */
    public static function historyRange(string $inicioDoPeriodo, int $meses): array
    {
        $base = new \DateTimeImmutable($inicioDoPeriodo);

        return [
            $base->modify('-' . $meses . ' months')->format('Y-m-d'),
            $base->modify('-1 day')->format('Y-m-d'),
        ];
    }

    /**
     * Quanto da meta sugerir para os funcionários: meta × participação ÷ 100.
     *
     * Nunca passa da meta. Hoje a participação vem de duas consultas com o
     * mesmo filtro e não chega a passar de 100%, mas se um dia as duas
     * divergirem a sugestão não pode virar um valor que o próprio servidor
     * recusa (dividir mais que a meta). A mesma conta está em
     * panel-goals.js (sugerirDivisao).
     */
    public static function suggestedSplit(float $metaLoja, ?float $participacaoPercent): ?float
    {
        if ($participacaoPercent === null || $metaLoja <= 0) {
            return null;
        }
        $centavos = (int) round($metaLoja * 100 * $participacaoPercent / 100);

        return min($centavos, (int) round($metaLoja * 100)) / 100;
    }
}
