<?php

namespace App\Domain\Finance;

/**
 * Análise de investimento: VPL, TIR e payback, aplicados à compra de estoque.
 *
 * Um fluxo de caixa é uma lista de valores por período, começando em t = 0:
 * saída negativa, entrada positiva. Comprar um lote de mercadoria é o caso
 * típico: paga-se tudo hoje (t = 0) e o dinheiro volta aos poucos, conforme
 * as peças vendem.
 *
 * VPL (valor presente líquido) a uma taxa i, a TMA da loja:
 *
 *     VPL = Σ FC_t / (1 + i)^t ,  t = 0 … n
 *
 * VPL > 0: a compra rende mais que a TMA; VPL < 0: o dinheiro renderia mais
 * na alternativa que a TMA representa.
 *
 * TIR (taxa interna de retorno) é a taxa que zera o VPL. Não há fórmula
 * fechada para n > 2, então ela é achada numericamente (bissecção).
 */
final class Investment
{
    /** Horizonte máximo da simulação de compra: 3 anos. */
    public const MAX_MESES = 36;

    /**
     * @param float       $taxaPercent taxa por período, em % (ex.: 2 para 2% a.m.)
     * @param list<float> $fluxos      FC_0, FC_1, …, FC_n
     */
    public static function npv(float $taxaPercent, array $fluxos): float
    {
        $i = $taxaPercent / 100;
        if ($i <= -1) {
            throw new \InvalidArgumentException('A taxa deve ser maior que -100%.');
        }
        $vpl = 0.0;
        foreach (array_values($fluxos) as $t => $fc) {
            $vpl += (float) $fc / (1 + $i) ** $t;
        }

        return round($vpl, 2);
    }

    /**
     * TIR em % por período, ou null quando ela não existe.
     *
     * Sem ao menos uma saída e uma entrada, nenhuma taxa zera o VPL (todo
     * fluxo positivo ou todo negativo). A busca é por bissecção: o VPL é
     * contínuo em i, então entre uma taxa com VPL positivo e outra com VPL
     * negativo existe uma raiz, e cortar o intervalo ao meio 200 vezes chega
     * a ela com folga muito abaixo do centavo.
     *
     * Num fluxo convencional (só uma troca de sinal: saída e depois entradas)
     * a TIR é única. Com mais de uma troca de sinal pode haver várias; aqui
     * vem a primeira encontrada a partir de −99%.
     *
     * @param list<float> $fluxos
     */
    public static function irr(array $fluxos): ?float
    {
        $fluxos = array_map('floatval', array_values($fluxos));
        $temSaida = false;
        $temEntrada = false;
        foreach ($fluxos as $fc) {
            $temSaida = $temSaida || $fc < 0;
            $temEntrada = $temEntrada || $fc > 0;
        }
        if (!$temSaida || !$temEntrada) {
            return null;
        }

        // VPL sem arredondar: a bissecção precisa do sinal exato perto da raiz.
        $vpl = static function (float $i) use ($fluxos): float {
            $s = 0.0;
            foreach ($fluxos as $t => $fc) {
                $s += $fc / (1 + $i) ** $t;
            }

            return $s;
        };

        $lo = -0.99;
        $hi = 1.0;
        $fLo = $vpl($lo);
        // Abre o intervalo até o VPL trocar de sinal (até 10.000% por período).
        while ($fLo * $vpl($hi) > 0 && $hi < 100) {
            $hi *= 2;
        }
        if ($fLo * $vpl($hi) > 0) {
            return null;
        }
        for ($k = 0; $k < 200; $k++) {
            $meio = ($lo + $hi) / 2;
            $fMeio = $vpl($meio);
            if ($fMeio === 0.0) {
                $lo = $hi = $meio;
                break;
            }
            if ($fLo * $fMeio < 0) {
                $hi = $meio;
            } else {
                $lo = $meio;
                $fLo = $fMeio;
            }
        }

        return round(($lo + $hi) / 2 * 100, 4);
    }

    /**
     * Em quantos períodos o investimento se paga, ou null se não se paga.
     *
     * Com taxa 0 é o payback simples; com a TMA, o descontado, que soma os
     * fluxos já trazidos a valor presente. Dentro do período em que o saldo
     * acumulado vira positivo, a fração é interpolada linearmente:
     * k − 1 + (quanto faltava) / (o que entrou no período k).
     *
     * @param list<float> $fluxos
     */
    public static function payback(array $fluxos, float $taxaPercent = 0.0): ?float
    {
        $i = $taxaPercent / 100;
        $acumulado = 0.0;
        foreach (array_values($fluxos) as $t => $fc) {
            $presente = (float) $fc / (1 + $i) ** $t;
            $antes = $acumulado;
            $acumulado += $presente;
            if ($t > 0 && $antes < 0 && $acumulado >= 0) {
                return round($t - 1 + (-$antes) / $presente, 2);
            }
        }

        return null;
    }

    /**
     * Fluxo de caixa mensal de comprar $quantidade peças de um produto.
     *
     * t = 0: paga quantidade × custo. Depois, as peças vendem no ritmo de
     * $vendaMensal por mês. Se ainda há $estoqueAtual na prateleira, ele vende
     * primeiro, e o lote novo só começa a voltar em dinheiro depois — é isso
     * que torna caro comprar cedo demais. Ao fim do mês k já saíram v·k peças
     * no total, das quais as do lote novo são
     *
     *     S(k) = mín(máx(v·k − estoque atual, 0), quantidade)
     *
     * e a entrada do mês k é (S(k) − S(k−1)) × preço. Peças que não vendem
     * dentro de $maxMeses não voltam: ficam em "sobra", fora do fluxo.
     *
     * @return array{fluxos: list<float>, meses: int, vendido: float, sobra: float, meses_ate_comecar: int}
     */
    public static function stockPurchaseFlows(
        float $quantidade,
        float $custoUnitario,
        float $precoUnitario,
        float $vendaMensal,
        float $estoqueAtual = 0.0,
        int $maxMeses = self::MAX_MESES
    ): array {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Informe quantas peças comprar.');
        }
        if ($custoUnitario <= 0) {
            throw new \InvalidArgumentException('O produto precisa ter custo para simular a compra.');
        }
        $estoqueAtual = max(0.0, $estoqueAtual);
        $fluxos = [-Money::fromCents(Money::toCents($quantidade * $custoUnitario))];
        $vendidoAntes = 0.0;
        $comecou = 0;
        $k = 0;
        if ($vendaMensal > 0) {
            for ($k = 1; $k <= $maxMeses; $k++) {
                $vendido = min(max($vendaMensal * $k - $estoqueAtual, 0.0), $quantidade);
                $noMes = $vendido - $vendidoAntes;
                if ($comecou === 0 && $noMes > 0) {
                    $comecou = $k;
                }
                $fluxos[] = Money::fromCents(Money::toCents($noMes * $precoUnitario));
                $vendidoAntes = $vendido;
                if ($vendido >= $quantidade) {
                    break;
                }
            }
            $k = min($k, $maxMeses);
        }

        return [
            'fluxos' => $fluxos,
            'meses' => $k,
            'vendido' => round($vendidoAntes, 2),
            'sobra' => round($quantidade - $vendidoAntes, 2),
            'meses_ate_comecar' => $comecou,
        ];
    }
}
