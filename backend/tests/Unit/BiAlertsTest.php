<?php

namespace Tests\Unit;

use App\Services\AnalyzingBIService;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/**
 * Alertas do BI: produtos parados, estoque crítico por variação e meta do mês.
 * Os valores esperados foram calculados à mão.
 */
final class BiAlertsTest extends TestCase
{
    private function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-06 12:00:00', new DateTimeZone('America/Sao_Paulo'));
    }

    /** @return array<string, mixed> */
    private function semVenda(int $id, string $nome, ?string $ultima, int $estoque = 10, float $custo = 5.0): array
    {
        return [
            'id' => $id,
            'name' => $nome,
            'stock_quantity' => $estoque,
            'cost_price' => $custo,
            'created_at' => '2026-08-08 12:00:00',
            'ultima_venda' => $ultima,
        ];
    }

    public function testProdutoQueNuncaVendeuEntraComoParado(): void
    {
        // 8 de agosto a 6 de novembro = 90 dias; 10 un. × R$ 5 = R$ 50 em custo.
        $p = AnalyzingBIService::montarProdutosParados([], [$this->semVenda(1, 'Boné', null)], $this->agora())[0];

        $this->assertSame(AnalyzingBIService::PARADO_NUNCA_VENDEU, $p['motivo']);
        $this->assertSame(90, $p['dias_sem_venda']);
        $this->assertSame(50.0, $p['capital_parado']);
    }

    public function testVendaAntigaContaDiasDesdeAUltimaVenda(): void
    {
        // 7 de outubro a 6 de novembro = 30 dias.
        $p = AnalyzingBIService::montarProdutosParados([], [$this->semVenda(1, 'Meia', '2026-10-07 12:00:00')], $this->agora())[0];

        $this->assertSame(AnalyzingBIService::PARADO_SEM_VENDA, $p['motivo']);
        $this->assertSame(30, $p['dias_sem_venda']);
    }

    public function testRegraDaQuedaContinuaValendo(): void
    {
        $linhas = [
            // 8 antes e 1 agora: limite = max(1; 2) = 2, e 1 < 2 → parado.
            ['product_id' => 1, 'product_name' => 'Caiu', 'qty_curr' => 1, 'qty_prev' => 8],
            // 8 antes e 2 agora: 2 não é menor que 2 → fora.
            ['product_id' => 2, 'product_name' => 'No limite', 'qty_curr' => 2, 'qty_prev' => 8],
            // Sem venda no período anterior: a queda não se aplica.
            ['product_id' => 3, 'product_name' => 'Novo', 'qty_curr' => 1, 'qty_prev' => 0],
        ];
        $lista = AnalyzingBIService::montarProdutosParados($linhas, [], $this->agora());

        $this->assertSame([1], array_column($lista, 'produto_id'));
        $this->assertSame(AnalyzingBIService::PARADO_QUEDA, $lista[0]['motivo']);
    }

    public function testProdutoEmDuasSituacoesApareceUmaVezNaMaisGrave(): void
    {
        $lista = AnalyzingBIService::montarProdutosParados(
            [['product_id' => 1, 'product_name' => 'Tênis', 'qty_curr' => 0, 'qty_prev' => 4]],
            [$this->semVenda(1, 'Tênis', '2026-10-05 12:00:00')],
            $this->agora()
        );

        $this->assertSame(1, count($lista));
        $this->assertSame(AnalyzingBIService::PARADO_SEM_VENDA, $lista[0]['motivo']);
    }

    public function testOrdemVaiDoPiorCasoAoMenosGrave(): void
    {
        $lista = AnalyzingBIService::montarProdutosParados(
            [['product_id' => 1, 'product_name' => 'Queda', 'qty_curr' => 0, 'qty_prev' => 4]],
            [
                $this->semVenda(2, 'Sem venda', '2026-09-01 12:00:00', 100, 100.0),
                $this->semVenda(3, 'Nunca barato', null, 1, 1.0),
                $this->semVenda(4, 'Nunca caro', null, 10, 50.0),
            ],
            $this->agora()
        );

        $this->assertSame([4, 3, 2, 1], array_column($lista, 'produto_id'));
    }

    public function testVariacaoZeradaEntraMesmoComTotalAcimaDoMinimo(): void
    {
        // P e G sobram, o M acabou: total 12, acima do mínimo 3.
        $lista = AnalyzingBIService::montarEstoqueCritico([], [
            ['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 12, 'min_stock' => 3, 'variant_type' => 'combinacao', 'variant_value' => 'Azul|M'],
            ['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 12, 'min_stock' => 3, 'variant_type' => 'tamanho', 'variant_value' => 'GG'],
        ]);

        $this->assertSame(1, count($lista));
        $this->assertSame(['Azul · M', 'GG'], $lista[0]['variacoes_esgotadas']);
    }

    public function testProdutoAbaixoDoMinimoJuntaAsVariacoesSemRepetir(): void
    {
        $lista = AnalyzingBIService::montarEstoqueCritico(
            [
                ['id' => 2, 'name' => 'Sem variação', 'stock_quantity' => 1, 'min_stock' => 2],
                ['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 3, 'min_stock' => 3],
            ],
            [['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 3, 'min_stock' => 3, 'variant_type' => 'combinacao', 'variant_value' => 'Azul|M']]
        );

        $this->assertSame([2, 1], array_column($lista, 'produto_id'));
        $this->assertSame([], $lista[0]['variacoes_esgotadas']);
        $this->assertSame(['Azul · M'], $lista[1]['variacoes_esgotadas']);
    }

    public function testProdutoEsgotadoNaoListaCadaVariacao(): void
    {
        $lista = AnalyzingBIService::montarEstoqueCritico(
            [['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 0, 'min_stock' => 3]],
            [['id' => 1, 'name' => 'Camiseta', 'stock_quantity' => 0, 'min_stock' => 3, 'variant_type' => 'combinacao', 'variant_value' => 'Azul|M']]
        );

        $this->assertSame([], $lista[0]['variacoes_esgotadas']);
    }

    public function testPercentualDaMeta(): void
    {
        // 750 de 1.000 = 75%; 1.250 de 1.000 passa de 100%.
        $this->assertSame(75.0, AnalyzingBIService::goalPercent(750, 1000));
        $this->assertSame(125.0, AnalyzingBIService::goalPercent(1250, 1000));
        $this->assertNull(AnalyzingBIService::goalPercent(750, null), 'sem meta definida');
        $this->assertNull(AnalyzingBIService::goalPercent(750, 0), 'meta zero não divide');
    }
}
