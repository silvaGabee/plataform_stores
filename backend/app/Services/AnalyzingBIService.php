<?php

namespace App\Services;

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
        $lastPrevMonth = $firstThisMonth->modify('-1 second')->setTime(23, 59, 59);

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
                'crescimento_percentual' => round(self::growthPercent($top['qty_curr'], $top['qty_prev']), 2),
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
                'variacao_percentual' => round(self::growthPercent($bottom['qty_curr'], $bottom['qty_prev']), 2),
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
                'serie_previsao' => null,
                'nota' => 'Reservado para linha de previsão (tracejada) e texto explicativo por IA.',
            ],
            'nota' => 'Estrutura opcional para POST futuro ao assistente de IA com o mesmo contexto do painel.',
        ];

        return [
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
     *   serie_previsao: null,
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

        return [
            'periodo' => $periodo,
            'granularidade' => $granularidade,
            'intervalo' => [
                'inicio' => $start->format('Y-m-d'),
                'fim' => $end->format('Y-m-d'),
            ],
            'serie' => $serie,
            'serie_previsao' => null,
            'meta' => [
                'status_pedidos' => ['pago', 'enviado'],
                'observacao_status' => 'No modelo atual, “concluído” no fluxo de venda corresponde a pedidos já pagos ou enviados; não há valor ENUM “concluido” na tabela.',
                'previsao_futura' => 'Campo serie_previsao reservado para alinhamento temporal com a série real (ex.: linha tracejada).',
            ],
        ];
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
