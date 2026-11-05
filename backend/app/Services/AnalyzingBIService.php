<?php

namespace App\Services;

use App\Domain\Finance\Trend;
use App\Repositories\AnalyzingBIRepository;
use DateTimeImmutable;
use DateTimeZone;

class AnalyzingBIService
{
    private AnalyzingBIRepository $repo;

    public function __construct(?AnalyzingBIRepository $repo = null)
    {
        $this->repo = $repo ?? new AnalyzingBIRepository();
    }

    /**
     * Monta o payload JSON do BI para uma loja.
     */
    public function buildPayload(int $storeId): array
    {
        $tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
        $now = new DateTimeImmutable('now', $tz);

        $firstThisMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $lastThisMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
        $firstPrevMonth = $firstThisMonth->modify('-1 month');
        // Comparação entre períodos EQUIVALENTES: do dia 1º até agora, contra
        // do dia 1º até o mesmo dia e hora do mês anterior. Comparar o mês
        // corrente (incompleto) com o anterior inteiro dava "queda de 88%"
        // todo início de mês e punha quase todo produto em "parados".
        $decorrido = $now->getTimestamp() - $firstThisMonth->getTimestamp();
        $fimPrevMonth = $firstThisMonth->modify('-1 second');
        $lastPrevMonth = $firstPrevMonth->modify('+' . $decorrido . ' seconds');
        if ($lastPrevMonth > $fimPrevMonth) {
            $lastPrevMonth = $fimPrevMonth; // ex.: dia 31 contra um mês de 30 dias
        }

        $currStart = $firstThisMonth->format('Y-m-d H:i:s');
        $currEnd = $lastThisMonth->format('Y-m-d H:i:s');
        $prevStart = $firstPrevMonth->format('Y-m-d H:i:s');
        $prevEnd = $lastPrevMonth->format('Y-m-d H:i:s');

        $valorTotal = $this->repo->sumPaidOrdersTotal($storeId, null, null);
        $valorMensal = $this->repo->sumPaidOrdersTotal($storeId, $currStart, $currEnd);
        $valorMensalAnterior = $this->repo->sumPaidOrdersTotal($storeId, $prevStart, $prevEnd);
        $quantidadePedidos = $this->repo->countPaidOrders($storeId, null, null);
        $ticketMedio = $quantidadePedidos > 0 ? $valorTotal / $quantidadePedidos : 0.0;
        $lucroEstimado = $this->repo->sumEstimatedProfit($storeId, null, null);

        $salesRows = $this->repo->fetchProductSalesCurrentVsPrevious($storeId, $currStart, $currEnd, $prevStart, $prevEnd);
        $lucroRows = $this->repo->fetchProductProfitByPeriod($storeId, $currStart, $currEnd);
        $critical = $this->repo->fetchCriticalStock($storeId);

        $lucroProdutos = [];
        foreach ($lucroRows as $row) {
            $qty = (float) ($row['quantidade_vendida'] ?? 0);
            $lucro = (float) ($row['lucro_total'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $custo = (float) ($row['custo_unitario'] ?? 0);
            $precoMedio = (float) ($row['preco_venda_medio'] ?? 0);
            $lucroProdutos[] = [
                'produto_id' => (int) $row['product_id'],
                'nome' => (string) $row['product_name'],
                'quantidade_vendida' => round($qty, 4),
                'custo_unitario' => round($custo, 2),
                'preco_venda_medio' => round($precoMedio, 2),
                'margem_unitaria' => round($precoMedio - $custo, 2),
                'lucro_total' => round($lucro, 2),
            ];
        }
        $lucroTop = array_slice($lucroProdutos, 0, 20);

        $produtoMais = new \stdClass();
        $produtoMenos = new \stdClass();
        $currPositive = [];
        foreach ($salesRows as $row) {
            $qc = (float) $row['qty_curr'];
            if ($qc > 0) {
                $currPositive[] = [
                    'product_id' => (int) $row['product_id'],
                    'nome' => (string) $row['product_name'],
                    'qty_curr' => $qc,
                    'qty_prev' => (float) $row['qty_prev'],
                ];
            }
        }
        if ($currPositive !== []) {
            usort($currPositive, static function (array $a, array $b): int {
                if ($a['qty_curr'] !== $b['qty_curr']) {
                    return $b['qty_curr'] <=> $a['qty_curr'];
                }

                return strcmp($a['nome'], $b['nome']);
            });
            $top = $currPositive[0];
            $produtoMais = (object) [
                'produto_id' => $top['product_id'],
                'nome' => $top['nome'],
                'quantidade_vendida' => round($top['qty_curr'], 4),
                'quantidade_periodo_anterior' => round($top['qty_prev'], 4),
                'crescimento_percentual' => self::productGrowth($top['qty_curr'], $top['qty_prev']),
            ];

            usort($currPositive, static function (array $a, array $b): int {
                if ($a['qty_curr'] !== $b['qty_curr']) {
                    return $a['qty_curr'] <=> $b['qty_curr'];
                }

                return strcmp($a['nome'], $b['nome']);
            });
            $bottom = $currPositive[0];
            $produtoMenos = (object) [
                'produto_id' => $bottom['product_id'],
                'nome' => $bottom['nome'],
                'quantidade_vendida' => round($bottom['qty_curr'], 4),
                'quantidade_periodo_anterior' => round($bottom['qty_prev'], 4),
                'variacao_percentual' => self::productGrowth($bottom['qty_curr'], $bottom['qty_prev']),
            ];
        }

        $produtosParados = [];
        foreach ($salesRows as $row) {
            $qc = (float) $row['qty_curr'];
            $qp = (float) $row['qty_prev'];
            if ($qp < 1) {
                continue;
            }
            $limite = max(1.0, $qp * 0.25);
            if ($qc < $limite) {
                $produtosParados[] = [
                    'produto_id' => (int) $row['product_id'],
                    'nome' => (string) $row['product_name'],
                    'quantidade_mes_atual' => round($qc, 4),
                    'quantidade_mes_anterior' => round($qp, 4),
                    'alerta' => true,
                ];
            }
        }
        usort($produtosParados, static function (array $a, array $b): int {
            return ($a['quantidade_mes_atual'] <=> $b['quantidade_mes_atual']) ?: strcmp($a['nome'], $b['nome']);
        });
        $produtosParados = array_slice($produtosParados, 0, 15);

        $estoqueCritico = [];
        foreach ($critical as $row) {
            $estoqueCritico[] = [
                'produto_id' => (int) $row['id'],
                'nome' => (string) $row['name'],
                'estoque_atual' => (float) $row['stock_quantity'],
                'estoque_minimo' => (float) $row['min_stock'],
            ];
        }

        $ideias = $this->buildIdeias(
            $valorMensal,
            $valorMensalAnterior,
            $produtoMais,
            $produtoMenos,
            $produtosParados,
            $estoqueCritico,
            $lucroTop
        );

        $resumoIa = [
            'versao' => 1,
            'gerado_em' => $now->format(DateTimeImmutable::ATOM),
            'indicadores' => [
                'valor_total' => round($valorTotal, 2),
                'valor_mensal' => round($valorMensal, 2),
                'quantidade_pedidos' => $quantidadePedidos,
                'ticket_medio' => round($ticketMedio, 2),
                'lucro_estimado' => round($lucroEstimado, 2),
            ],
            'faturamento_tempo' => [
                'endpoint' => 'GET /api/loja/{slug}/analyzing-bi/faturamento?periodo=7d|30d|3m',
                'nota' => 'A série do endpoint traz serie_previsao: tendência por mínimos quadrados sobre períodos completos.',
            ],
            'nota' => 'Estrutura opcional para POST futuro ao assistente de IA com o mesmo contexto do painel.',
        ];

        // PIX à vista x parcelamento: o que a loja abriu mão no mês, o que
        // recebeu de juros e quanto o desconto vale como taxa mensal.
        $store = (new \App\Repositories\StoreRepository())->find($storeId) ?? [];
        $regras = \App\Services\InstallmentPlan::rules($store);
        $pixPct = \App\Services\OrderPricing::pixPct($store);
        $pixParcelamento = $this->repo->pixVsInstallments($storeId, $currStart, $currEnd) + [
            'pix_pct' => $pixPct,
            'parcelas_sem_juros' => $regras['free'],
            'taxa_parcelamento' => $regras['rate'],
            'taxa_equivalente_pix' => \App\Domain\Finance\CashDiscount::taxaEquivalente($pixPct, $regras['free']),
        ];

        return [
            'pix_parcelamento' => $pixParcelamento,
            'valor_total' => round($valorTotal, 2),
            'valor_mensal' => round($valorMensal, 2),
            'quantidade_pedidos' => $quantidadePedidos,
            'ticket_medio' => round($ticketMedio, 2),
            'lucro_estimado' => round($lucroEstimado, 2),
            'lucro_produtos' => $lucroTop,
            'produto_mais_vendido' => $produtoMais,
            'produto_menos_vendido' => $produtoMenos,
            'produtos_parados' => $produtosParados,
            'estoque_critico' => $estoqueCritico,
            'ideias_investimento' => $ideias,
            'resumo_para_ia' => $resumoIa,
        ];
    }

    /**
     * @param object|array<string,mixed> $produtoMais
     */
    private function buildIdeias(
        float $valorMensal,
        float $valorMensalAnterior,
        $produtoMais,
        $produtoMenos,
        array $produtosParados,
        array $estoqueCritico,
        array $lucroTop
    ): array {
        $ideias = [];
        $pctVendasMes = self::growthPercent($valorMensal, $valorMensalAnterior);
        if ($valorMensalAnterior > 0 || $valorMensal > 0) {
            if ($pctVendasMes > 1) {
                $ideias[] = 'As vendas do mês cresceram ' . round($pctVendasMes, 1) . '% em relação ao mês anterior. Bom momento para reforçar estoque dos itens campeões.';
            } elseif ($pctVendasMes < -1) {
                $ideias[] = 'As vendas do mês recuaram ' . round(abs($pctVendasMes), 1) . '% em relação ao mês anterior. Avalie campanhas ou mix de produtos.';
            } else {
                $ideias[] = 'As vendas do mês estão estáveis em relação ao mês anterior.';
            }
        }

        if (is_object($produtoMais) && isset($produtoMais->nome) && (float) ($produtoMais->quantidade_vendida ?? 0) > 0) {
            $nome = (string) $produtoMais->nome;
            $ideias[] = '"' . $nome . '" tem alta saída neste mês. Considere aumentar o estoque.';
        }

        foreach (array_slice($produtosParados, 0, 3) as $p) {
            $ideias[] = '"' . ($p['nome'] ?? '') . '" está com pouca saída neste mês frente ao anterior. Considere promoção ou destaque na vitrine.';
        }

        foreach (array_slice($estoqueCritico, 0, 3) as $e) {
            $ideias[] = '"' . ($e['nome'] ?? '') . '" está com estoque crítico. Reposição recomendada.';
        }

        foreach (array_slice($lucroTop, 0, 3) as $lp) {
            $lucro = (float) ($lp['lucro_total'] ?? 0);
            if ($lucro > 0) {
                $ideias[] = '"' . ($lp['nome'] ?? '') . '" gerou R$ ' . number_format($lucro, 2, ',', '.') . ' de lucro neste mês. Vale manter estoque e destaque na vitrine.';
            }
        }

        if (is_object($produtoMenos) && isset($produtoMenos->nome) && (float) ($produtoMenos->quantidade_vendida ?? 0) > 0) {
            $nome = (string) $produtoMenos->nome;
            $var = (float) ($produtoMenos->variacao_percentual ?? 0);
            if ($var < -5) {
                $ideias[] = '"' . $nome . '" teve queda forte nas vendas vs. mês anterior. Investigue preço, visibilidade ou sazonalidade.';
            }
        }

        $out = [];
        foreach ($ideias as $s) {
            $s = trim((string) $s);
            if ($s !== '' && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }

        return array_slice($out, 0, 12);
    }

    /**
     * Variação de um produto entre os dois períodos, ou null sem base.
     *
     * Se o produto não vendeu nada no período anterior, (V − 0) / 0 não existe.
     * growthPercent() responde 100% por convenção, o que serve ao faturamento
     * da loja; num card de produto, "100%" verde para quem vendeu 1 unidade
     * contra 0 parecia crescimento expressivo. Aqui a tela recebe null e diz
     * que não havia venda no período de comparação.
     */
    public static function productGrowth(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(self::growthPercent($current, $previous), 2) : null;
    }

    public static function growthPercent(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100.0;
    }

    /**
     * Série de faturamento (pedidos pagos ou enviados) por dia ou mês, só da loja.
     *
     * @param string $periodo Um de: 7d, 30d, 3m
     *
     * @return array{
     *   periodo: string,
     *   granularidade: string,
     *   intervalo: array{inicio: string, fim: string},
     *   serie: list<array{data: string, valor: float}>,
     *   serie_previsao: list<array{data: string, valor: float}>|null,
     *   meta: array<string, mixed>
     * }
     */
    public function getFaturamentoAoLongoDoTempo(int $storeId, string $periodo): array
    {
        $periodo = strtolower(trim($periodo));
        if (!in_array($periodo, ['7d', '30d', '3m'], true)) {
            $periodo = '30d';
        }

        $tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
        $now = new DateTimeImmutable('now', $tz);

        if ($periodo === '7d') {
            $start = $now->modify('-6 days')->setTime(0, 0, 0);
            $granularidade = 'dia';
        } elseif ($periodo === '30d') {
            $start = $now->modify('-29 days')->setTime(0, 0, 0);
            $granularidade = 'dia';
        } else {
            $start = $now->modify('first day of this month')->modify('-2 months')->setTime(0, 0, 0);
            $granularidade = 'mes';
        }

        $end = $now->setTime(23, 59, 59);
        $fromSql = $start->format('Y-m-d H:i:s');
        $toSql = $end->format('Y-m-d H:i:s');

        $granularity = $granularidade === 'mes' ? 'month' : 'day';
        $rows = $this->repo->fetchRevenueByPeriod($storeId, $fromSql, $toSql, $granularity);
        $serie = $granularidade === 'mes'
            ? $this->fillRevenueSeriesMonths($rows, $start, $end)
            : $this->fillRevenueSeriesDays($rows, $start, $end);

        $previsao = $this->buildForecast($storeId, $periodo, $granularidade, $start, $now);

        return [
            'periodo' => $periodo,
            'granularidade' => $granularidade,
            'intervalo' => [
                'inicio' => $start->format('Y-m-d'),
                'fim' => $end->format('Y-m-d'),
            ],
            'serie' => $serie,
            'serie_previsao' => $previsao['serie'],
            'meta' => [
                'status_pedidos' => ['pago', 'enviado'],
                'observacao_status' => 'No modelo atual, “concluído” no fluxo de venda corresponde a pedidos já pagos ou enviados; não há valor ENUM “concluido” na tabela.',
                'previsao' => $previsao['meta'],
            ],
        ];
    }

    /** Quantos períodos projetar, contando o período corrente (incompleto). */
    private const HORIZONTE = ['7d' => 3, '30d' => 7, '3m' => 2];

    /** Meses completos usados no ajuste mensal (o gráfico mostra só 3). */
    private const MESES_AJUSTE = 6;

    /**
     * Linha de tendência por mínimos quadrados (ver App\Domain\Finance\Trend).
     *
     * A reta é ajustada só com períodos COMPLETOS: o dia de hoje e o mês
     * corrente ainda estão acontecendo, e um valor parcial no fim da série
     * puxaria a inclinação para baixo — a tendência mostraria queda toda
     * manhã. O período corrente entra na projeção, não no ajuste.
     *
     * A série devolvida começa no último período completo, com o valor REAL
     * dele (é onde a linha tracejada se apoia na linha cheia), e segue com os
     * valores projetados.
     *
     * @return array{serie: list<array{data: string, valor: float}>|null, meta: array<string, mixed>}
     */
    private function buildForecast(int $storeId, string $periodo, string $granularidade, DateTimeImmutable $start, DateTimeImmutable $now): array
    {
        $mensal = $granularidade === 'mes';
        $inicioCorrente = $mensal
            ? $now->modify('first day of this month')->setTime(0, 0, 0)
            : $now->setTime(0, 0, 0);
        $fimAjuste = $inicioCorrente->modify('-1 second');
        $inicioAjuste = $mensal
            ? $inicioCorrente->modify('-' . self::MESES_AJUSTE . ' months')
            : $start;

        $rows = $this->repo->fetchRevenueByPeriod(
            $storeId,
            $inicioAjuste->format('Y-m-d H:i:s'),
            $fimAjuste->format('Y-m-d H:i:s'),
            $mensal ? 'month' : 'day'
        );
        $base = $mensal
            ? $this->fillRevenueSeriesMonths($rows, $inicioAjuste, $fimAjuste)
            : $this->fillRevenueSeriesDays($rows, $inicioAjuste, $fimAjuste);
        $valores = array_column($base, 'valor');

        $meta = [
            'metodo' => 'regressao_linear_minimos_quadrados',
            'periodos_ajuste' => count($valores),
            'ajuste_inicio' => $inicioAjuste->format('Y-m-d'),
            'ajuste_fim' => $fimAjuste->format('Y-m-d'),
            'observacao' => 'Ajuste só com períodos completos; o período corrente é projetado, não usado no ajuste.',
        ];

        $reg = Trend::linearRegression($valores);
        // Sem venda nenhuma no período de ajuste não há tendência a mostrar.
        if ($reg === null || array_sum($valores) <= 0) {
            $meta['motivo_sem_previsao'] = $reg === null ? 'periodos_insuficientes' : 'sem_vendas_no_periodo';

            return ['serie' => null, 'meta' => $meta];
        }

        $passo = $mensal ? 'first day of next month' : '+1 day';
        $formato = $mensal ? 'Y-m-01' : 'Y-m-d';
        $ultimo = end($base);
        $serie = [['data' => $ultimo['data'], 'valor' => $ultimo['valor']]];
        $cur = $inicioCorrente;
        foreach (Trend::forecast($valores, self::HORIZONTE[$periodo] ?? 3) as $v) {
            $serie[] = ['data' => $cur->format($formato), 'valor' => $v];
            $cur = $cur->modify($passo);
        }

        $meta['coeficientes'] = [
            'a' => round($reg['a'], 4),
            'b' => round($reg['b'], 4),
        ];
        $meta['inclinacao_por_periodo'] = round($reg['b'], 2);
        $meta['r2'] = round($reg['r2'], 4);

        return ['serie' => $serie, 'meta' => $meta];
    }

    /**
     * @param list<array{data: string, valor: float}> $rows
     *
     * @return list<array{data: string, valor: float}>
     */
    private function fillRevenueSeriesDays(array $rows, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['data']] = (float) $r['valor'];
        }
        $out = [];
        $cur = $start;
        while ($cur <= $end) {
            $key = $cur->format('Y-m-d');
            $out[] = [
                'data' => $key,
                'valor' => round($map[$key] ?? 0.0, 2),
            ];
            $cur = $cur->modify('+1 day');
        }

        return $out;
    }

    /**
     * @param list<array{data: string, valor: float}> $rows
     *
     * @return list<array{data: string, valor: float}>
     */
    private function fillRevenueSeriesMonths(array $rows, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['data']] = (float) $r['valor'];
        }
        $out = [];
        $cur = $start->setTime(0, 0, 0);
        $endYm = (int) $end->format('Ym');
        while ((int) $cur->format('Ym') <= $endYm) {
            $key = $cur->format('Y-m-01');
            $out[] = [
                'data' => $key,
                'valor' => round($map[$key] ?? 0.0, 2),
            ];
            $cur = $cur->modify('first day of next month');
        }

        return $out;
    }
}
