<?php

namespace App\Services;

use App\Domain\Finance\ProductMetrics;
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
        // Mesmo intervalo do faturamento do mês e do lucro por produto: é o número
        // que se compara com eles. O acumulado acima cobre todo o período.
        $lucroMensal = $this->repo->sumEstimatedProfit($storeId, $currStart, $currEnd);
        $metaMes = $this->repo->fetchStoreGoal($storeId, $now->format('Y-m'));

        $salesRows = $this->repo->fetchProductSalesCurrentVsPrevious($storeId, $currStart, $currEnd, $prevStart, $prevEnd);
        $lucroRows = $this->repo->fetchProductProfitByPeriod($storeId, $currStart, $currEnd);
        $critical = $this->repo->fetchCriticalStock($storeId);
        $semVendaRows = $this->repo->fetchProductsWithoutSalesSince(
            $storeId,
            $now->modify('-' . self::DIAS_SEM_VENDA . ' days')->format('Y-m-d H:i:s')
        );

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

        $todosParados = self::montarProdutosParados($salesRows, $semVendaRows, $now);
        $produtosParados = array_slice($todosParados, 0, 15);

        $estoqueCritico = self::montarEstoqueCritico($critical, $this->repo->fetchZeroedVariants($storeId));

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
                'lucro_mensal' => round($lucroMensal, 2),
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
            'lucro_mensal' => round($lucroMensal, 2),
            // Como L = R − CMV, o custo do que foi vendido é R − L.
            'margem_mes' => ProductMetrics::marginPercent($valorMensal, $lucroMensal),
            'markup_mes' => ProductMetrics::markupPercent($valorMensal - $lucroMensal, $lucroMensal),
            'meta_mes' => [
                'meta' => $metaMes,
                'realizado' => round($valorMensal, 2),
                'percentual' => self::goalPercent($valorMensal, $metaMes),
            ],
            'lucro_produtos' => $lucroTop,
            'produto_mais_vendido' => $produtoMais,
            'produto_menos_vendido' => $produtoMenos,
            'produtos_parados' => $produtosParados,
            'produtos_parados_total' => count($todosParados),
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
            $nome = '"' . ($p['nome'] ?? '') . '"';
            if (($p['motivo'] ?? '') === self::PARADO_NUNCA_VENDEU) {
                $ideias[] = $nome . ' nunca vendeu e está no cadastro há ' . (int) $p['dias_sem_venda'] . ' dias. Reveja o preço e a foto, ou faça uma promoção.';
            } elseif (($p['motivo'] ?? '') === self::PARADO_SEM_VENDA) {
                $ideias[] = $nome . ' está há ' . (int) $p['dias_sem_venda'] . ' dias sem vender. Considere promoção ou destaque na vitrine.';
            } else {
                $ideias[] = $nome . ' está com pouca saída neste mês frente ao anterior. Considere promoção ou destaque na vitrine.';
            }
        }

        foreach (array_slice($estoqueCritico, 0, 3) as $e) {
            $ideias[] = (float) ($e['estoque_atual'] ?? 0) > (float) ($e['estoque_minimo'] ?? 0)
                ? '"' . ($e['nome'] ?? '') . '" tem variações esgotadas: ' . implode(', ', $e['variacoes_esgotadas'] ?? []) . '. Reposição recomendada.'
                : '"' . ($e['nome'] ?? '') . '" está com estoque crítico. Reposição recomendada.';
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

    /** Período da Curva ABC e do giro: um ano cobre as estações. */
    private const MESES_INDICADORES = 12;
    /** Ritmo de venda usado na cobertura: recente, para acompanhar a demanda atual. */
    private const DIAS_JANELA_COBERTURA = 90;
    /** Período do impacto dos cupons. */
    private const DIAS_CUPONS = 90;

    /**
     * Indicadores por produto e de cupons. Ficam fora de buildPayload() por lerem
     * doze meses de itens: a tela mostra os cartões e carrega estes depois.
     *
     * @return array<string, mixed>
     */
    public function buildIndicators(int $storeId): array
    {
        $tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
        $now = new DateTimeImmutable('now', $tz);
        $linhas = $this->repo->fetchProductIndicators(
            $storeId,
            $now->modify('-' . self::MESES_INDICADORES . ' months')->format('Y-m-d H:i:s'),
            $now->modify('-' . self::DIAS_JANELA_COBERTURA . ' days')->format('Y-m-d H:i:s')
        );
        $parados = self::montarProdutosParados([], $this->repo->fetchProductsWithoutSalesSince(
            $storeId,
            $now->modify('-' . self::DIAS_SEM_VENDA . ' days')->format('Y-m-d H:i:s')
        ), $now);

        return [
            'capital' => [
                'parado' => round(array_sum(array_column($parados, 'capital_parado')), 2),
                'dias_sem_venda' => self::DIAS_SEM_VENDA,
            ] + self::resumoCapital($linhas),
            'abc' => self::montarAbc($linhas),
            'estoque' => self::montarEstoque($linhas, $now),
            'cupons' => self::montarCupons($this->repo->fetchCouponImpact(
                $storeId,
                $now->modify('-' . self::DIAS_CUPONS . ' days')->format('Y-m-d H:i:s')
            )),
            'periodo' => [
                'meses_abc' => self::MESES_INDICADORES,
                'dias_cobertura' => self::DIAS_JANELA_COBERTURA,
                'dias_cupons' => self::DIAS_CUPONS,
            ],
        ];
    }

    /**
     * Capital em estoque: Σ estoque × custo atual do cadastro. Produto com estoque e
     * sem custo entra com zero, então é contado à parte: o total está subestimado.
     *
     * @param list<array<string, mixed>> $linhas
     * @return array{total: float, unidades: int, sem_custo: int}
     */
    public static function resumoCapital(array $linhas): array
    {
        $total = 0.0;
        $unidades = 0;
        $semCusto = 0;
        foreach ($linhas as $l) {
            $estoque = max(0, (int) $l['stock_quantity']);
            $custo = (float) ($l['cost_price'] ?? 0);
            $total += $estoque * $custo;
            $unidades += $estoque;
            if ($estoque > 0 && $custo <= 0) {
                $semCusto++;
            }
        }

        return ['total' => round($total, 2), 'unidades' => $unidades, 'sem_custo' => $semCusto];
    }

    /**
     * @param list<array<string, mixed>> $linhas
     * @return array{itens: list<array<string, mixed>>, resumo: array<string, array{produtos: int, receita: float, percentual: float}>}
     */
    public static function montarAbc(array $linhas): array
    {
        $itens = [];
        foreach (ProductMetrics::abc($linhas) as $l) {
            $receita = (float) $l['receita'];
            $cmv = (float) $l['cmv'];
            $itens[] = [
                'produto_id' => (int) $l['id'],
                'nome' => (string) $l['name'],
                'classe' => $l['classe'],
                'receita' => round($receita, 2),
                'participacao' => $l['participacao'],
                'acumulado' => $l['acumulado'],
                'margem' => ProductMetrics::marginPercent($receita, $receita - $cmv),
                'markup' => ProductMetrics::markupPercent($cmv, $receita - $cmv),
            ];
        }
        $total = array_sum(array_column($itens, 'receita'));
        $resumo = [];
        foreach (['A', 'B', 'C'] as $classe) {
            $daClasse = array_filter($itens, static fn (array $i): bool => $i['classe'] === $classe);
            $receita = array_sum(array_column($daClasse, 'receita'));
            $resumo[$classe] = [
                'produtos' => count($daClasse),
                'receita' => round($receita, 2),
                'percentual' => $total > 0 ? round($receita / $total * 100, 1) : 0.0,
            ];
        }

        return ['itens' => $itens, 'resumo' => $resumo];
    }

    /**
     * Capital, giro e cobertura por produto.
     *
     * A janela do ritmo de venda é a menor entre 90 dias e a idade do produto: um
     * item cadastrado há 10 dias que vendeu 10 vende 1 por dia, não 1 a cada 9.
     * A idade conta da venda mais antiga, se ela vier antes do cadastro, como em
     * histórico importado: senão, o ritmo seria dividido por quase nenhum dia.
     * O giro usa o estoque atual porque o sistema não guarda o estoque médio.
     *
     * @param list<array<string, mixed>> $linhas
     * @return list<array<string, mixed>>
     */
    public static function montarEstoque(array $linhas, DateTimeImmutable $now): array
    {
        $itens = [];
        foreach ($linhas as $l) {
            $estoque = max(0, (int) $l['stock_quantity']);
            $minimo = max(0, (int) $l['min_stock']);
            $custo = (float) ($l['cost_price'] ?? 0);
            $vendido = (float) $l['quantidade_janela'];
            $inicio = min(array_filter([(string) $l['created_at'], (string) ($l['primeira_venda'] ?? '')]));
            $criado = new DateTimeImmutable($inicio, $now->getTimezone());
            $idade = (int) floor(($now->getTimestamp() - $criado->getTimestamp()) / 86400);
            $janela = max(1, min(self::DIAS_JANELA_COBERTURA, $idade));
            $diasRepor = ProductMetrics::daysUntil($estoque, $minimo, $vendido, $janela);
            $itens[] = [
                'produto_id' => (int) $l['id'],
                'nome' => (string) $l['name'],
                'estoque' => $estoque,
                'estoque_minimo' => $minimo,
                'capital' => round($estoque * $custo, 2),
                'giro' => ProductMetrics::turnover((float) $l['cmv'], $estoque * $custo),
                'vendido_janela' => $vendido,
                'cobertura_dias' => ProductMetrics::daysUntil($estoque, 0, $vendido, $janela),
                'repor_em_dias' => $diasRepor,
                'repor_em' => $diasRepor === null ? null : $now->modify('+' . $diasRepor . ' days')->format('Y-m-d'),
            ];
        }

        return $itens;
    }

    /**
     * Cupons: quanto foi dado de desconto e o que sobrou de receita e lucro.
     *
     * A comparação com os pedidos sem cupom descreve os dois grupos, mas não mede
     * venda a mais: não há como saber quem compraria igual sem o desconto.
     *
     * @param list<array<string, mixed>> $linhas de fetchCouponImpact()
     * @return array{cupons: list<array<string, mixed>>, com_cupom: array<string, mixed>, sem_cupom: array<string, mixed>}
     */
    public static function montarCupons(array $linhas): array
    {
        $grupo = static function (array $ls): array {
            $pedidos = (int) array_sum(array_column($ls, 'pedidos'));
            $receita = (float) array_sum(array_column($ls, 'receita'));
            $lucro = (float) array_sum(array_column($ls, 'lucro'));

            return [
                'pedidos' => $pedidos,
                'desconto' => round((float) array_sum(array_column($ls, 'desconto')), 2),
                'receita' => round($receita, 2),
                'lucro' => round($lucro, 2),
                'ticket_medio' => $pedidos > 0 ? round($receita / $pedidos, 2) : 0.0,
                'margem' => ProductMetrics::marginPercent($receita, $lucro),
            ];
        };
        $com = array_values(array_filter($linhas, static fn (array $l): bool => $l['coupon_code'] !== null));
        $sem = array_values(array_filter($linhas, static fn (array $l): bool => $l['coupon_code'] === null));
        $cupons = array_map(static fn (array $l): array => ['codigo' => (string) $l['coupon_code']] + $grupo([$l]), $com);
        usort($cupons, static fn (array $a, array $b): int => $b['desconto'] <=> $a['desconto']);

        return ['cupons' => $cupons, 'com_cupom' => $grupo($com), 'sem_cupom' => $grupo($sem)];
    }

    /** Dias sem nenhuma venda paga para um produto com estoque contar como parado. */
    public const DIAS_SEM_VENDA = 30;

    public const PARADO_NUNCA_VENDEU = 'nunca_vendeu';
    public const PARADO_SEM_VENDA = 'sem_venda';
    public const PARADO_QUEDA = 'queda';

    /**
     * Junta as três situações de produto parado, da pior para a menos grave:
     *
     *   nunca_vendeu  tem estoque, está no cadastro há 30 dias ou mais e nunca vendeu;
     *   sem_venda     tem estoque e a última venda foi há 30 dias ou mais;
     *   queda         vendeu no período anterior e, neste, menos de 25% daquilo.
     *
     * A regra da queda sozinha só via quem vendeu no mês anterior. Um produto
     * entra uma vez só: se estiver em duas situações, vale a mais grave. Dentro de
     * nunca_vendeu e sem_venda, vem primeiro o que prende mais dinheiro.
     *
     * @param list<array<string, mixed>> $salesRows de fetchProductSalesCurrentVsPrevious()
     * @param list<array<string, mixed>> $semVendaRows de fetchProductsWithoutSalesSince()
     * @return list<array<string, mixed>>
     */
    public static function montarProdutosParados(array $salesRows, array $semVendaRows, DateTimeImmutable $now): array
    {
        $porProduto = [];
        foreach ($semVendaRows as $row) {
            $id = (int) $row['id'];
            $ultima = $row['ultima_venda'] ?? null;
            $referencia = new DateTimeImmutable((string) ($ultima ?? $row['created_at']), $now->getTimezone());
            $estoque = (float) $row['stock_quantity'];
            $porProduto[$id] = [
                'produto_id' => $id,
                'nome' => (string) $row['name'],
                'motivo' => $ultima === null ? self::PARADO_NUNCA_VENDEU : self::PARADO_SEM_VENDA,
                'dias_sem_venda' => (int) floor(($now->getTimestamp() - $referencia->getTimestamp()) / 86400),
                'estoque_atual' => $estoque,
                'capital_parado' => round($estoque * (float) ($row['cost_price'] ?? 0), 2),
                'quantidade_mes_atual' => 0.0,
                'quantidade_mes_anterior' => 0.0,
                'alerta' => true,
            ];
        }

        foreach ($salesRows as $row) {
            $id = (int) $row['product_id'];
            $qc = (float) $row['qty_curr'];
            $qp = (float) $row['qty_prev'];
            if (isset($porProduto[$id]) || $qp < 1 || $qc >= max(1.0, $qp * 0.25)) {
                continue;
            }
            $porProduto[$id] = [
                'produto_id' => $id,
                'nome' => (string) $row['product_name'],
                'motivo' => self::PARADO_QUEDA,
                'dias_sem_venda' => null,
                'estoque_atual' => null,
                'capital_parado' => null,
                'quantidade_mes_atual' => round($qc, 4),
                'quantidade_mes_anterior' => round($qp, 4),
                'alerta' => true,
            ];
        }

        $gravidade = [self::PARADO_NUNCA_VENDEU => 0, self::PARADO_SEM_VENDA => 1, self::PARADO_QUEDA => 2];
        $lista = array_values($porProduto);
        usort($lista, static function (array $a, array $b) use ($gravidade): int {
            return ($gravidade[$a['motivo']] <=> $gravidade[$b['motivo']])
                ?: ((float) $b['capital_parado'] <=> (float) $a['capital_parado'])
                ?: ($a['quantidade_mes_atual'] <=> $b['quantidade_mes_atual'])
                ?: strcmp($a['nome'], $b['nome']);
        });

        return $lista;
    }

    /**
     * Estoque crítico: total no mínimo ou abaixo dele, ou alguma variação zerada.
     *
     * Quando o produto inteiro acabou, a lista de variações fica vazia: "esgotado"
     * já diz tudo. Os produtos abaixo do mínimo vêm primeiro, na ordem recebida.
     *
     * @param list<array<string, mixed>> $abaixoDoMinimo de fetchCriticalStock()
     * @param list<array<string, mixed>> $variacoesZeradas de fetchZeroedVariants()
     * @return list<array{produto_id: int, nome: string, estoque_atual: float, estoque_minimo: float, variacoes_esgotadas: list<string>}>
     */
    public static function montarEstoqueCritico(array $abaixoDoMinimo, array $variacoesZeradas): array
    {
        $porProduto = [];
        foreach (array_merge($abaixoDoMinimo, $variacoesZeradas) as $row) {
            $id = (int) $row['id'];
            if (!isset($porProduto[$id])) {
                $porProduto[$id] = [
                    'produto_id' => $id,
                    'nome' => (string) $row['name'],
                    'estoque_atual' => (float) $row['stock_quantity'],
                    'estoque_minimo' => (float) $row['min_stock'],
                    'variacoes_esgotadas' => [],
                ];
            }
            if (isset($row['variant_value']) && (float) $row['stock_quantity'] > 0) {
                // A matriz grava "Cor|Tamanho".
                $porProduto[$id]['variacoes_esgotadas'][] = str_replace('|', ' · ', (string) $row['variant_value']);
            }
        }

        return array_values($porProduto);
    }

    /** Percentual atingido da meta, ou null quando não há meta para comparar. */
    public static function goalPercent(float $realizado, ?float $meta): ?float
    {
        return $meta !== null && $meta > 0 ? round($realizado / $meta * 100, 1) : null;
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
