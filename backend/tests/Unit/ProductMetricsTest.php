<?php

namespace Tests\Unit;

use App\Domain\Finance\ProductMetrics;
use App\Domain\Product\Barcode;
use App\Services\AnalyzingBIService;
use App\Services\CustomerReport;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/**
 * Margem, markup, Curva ABC, giro, cobertura, cupons, clientes e código de barras.
 * Os valores esperados foram calculados à mão.
 */
final class ProductMetricsTest extends TestCase
{
    private function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-06 12:00:00', new DateTimeZone('America/Sao_Paulo'));
    }

    public function testMargemEMarkupDoQuadroUm(): void
    {
        // Custo 60, venda 100, lucro 40: margem 40 / 100 e markup 40 / 60.
        $this->assertSame(40.0, ProductMetrics::marginPercent(100, 40));
        $this->assertSame(66.67, ProductMetrics::markupPercent(60, 40));
    }

    public function testMargemEMarkupSemBaseSaoNulos(): void
    {
        $this->assertNull(ProductMetrics::marginPercent(0, 0), 'sem receita');
        $this->assertNull(ProductMetrics::markupPercent(0, 50), 'sem custo registrado');
    }

    public function testCurvaAbcClassificaPeloAcumuladoAnterior(): void
    {
        // Total 1.000: 700 (70%), 150 (85%), 100 (95%), 50 (100%).
        // Antes de cada item: 0% → A; 70% → A; 85% → B; 95% → C.
        $itens = ProductMetrics::abc([
            ['id' => 4, 'receita' => 50],
            ['id' => 1, 'receita' => 700],
            ['id' => 3, 'receita' => 100],
            ['id' => 2, 'receita' => 150],
        ]);

        $this->assertSame([1, 2, 3, 4], array_column($itens, 'id'));
        $this->assertSame(['A', 'A', 'B', 'C'], array_column($itens, 'classe'));
        $this->assertSame([70.0, 85.0, 95.0, 100.0], array_column($itens, 'acumulado'));
    }

    public function testProdutoDominanteFicaNaClasseA(): void
    {
        $itens = ProductMetrics::abc([['id' => 1, 'receita' => 900], ['id' => 2, 'receita' => 100]]);

        $this->assertSame(['A', 'B'], array_column($itens, 'classe'), 'o item que cruza os 80% continua A');
    }

    public function testProdutoSemReceitaFicaForaDaCurva(): void
    {
        $this->assertSame([], ProductMetrics::abc([['id' => 1, 'receita' => 0]]));
    }

    public function testCoberturaDoExemploDeQuarentaECincoDias(): void
    {
        // 60 vendidos em 90 dias = 2/3 por dia; 30 em estoque duram 45 dias.
        $this->assertSame(45, ProductMetrics::daysUntil(30, 0, 60, 90));
    }

    public function testReporConsideraOEstoqueMinimo(): void
    {
        // Estoque 30, mínimo 10: faltam 20 unidades, a 2/3 por dia = 30 dias.
        $this->assertSame(30, ProductMetrics::daysUntil(30, 10, 60, 90));
        $this->assertSame(0, ProductMetrics::daysUntil(5, 10, 60, 90), 'já abaixo do mínimo');
    }

    public function testSemVendaNaoHaCobertura(): void
    {
        $this->assertNull(ProductMetrics::daysUntil(30, 0, 0, 90));
        $this->assertSame(0, ProductMetrics::daysUntil(0, 0, 0, 90), 'esgotado dura zero dias');
    }

    public function testGiro(): void
    {
        // CMV de R$ 1.200 sobre R$ 400 em estoque: girou 3 vezes.
        $this->assertSame(3.0, ProductMetrics::turnover(1200, 400));
        $this->assertNull(ProductMetrics::turnover(1200, 0), 'sem estoque a custo');
    }

    public function testCapitalEmEstoqueAvisaProdutoSemCusto(): void
    {
        // 10 × 5 + 2 × 100 = 250; o de custo zero soma unidades, mas nenhum valor.
        $r = AnalyzingBIService::resumoCapital([
            ['stock_quantity' => 10, 'cost_price' => 5],
            ['stock_quantity' => 2, 'cost_price' => 100],
            ['stock_quantity' => 3, 'cost_price' => 0],
            ['stock_quantity' => 0, 'cost_price' => 0],
        ]);

        $this->assertSame(250.0, $r['total']);
        $this->assertSame(15, $r['unidades']);
        $this->assertSame(1, $r['sem_custo'], 'estoque zerado sem custo não prende dinheiro');
    }

    public function testAbcMostraMargemEMarkupPorProduto(): void
    {
        // Receita 100 e CMV 60: lucro 40, margem 40%, markup 66,67%.
        $abc = AnalyzingBIService::montarAbc([['id' => 1, 'name' => 'Boné', 'receita' => 100, 'cmv' => 60]]);

        $this->assertSame(40.0, $abc['itens'][0]['margem']);
        $this->assertSame(66.67, $abc['itens'][0]['markup']);
        $this->assertSame(100.0, $abc['resumo']['A']['percentual']);
    }

    public function testEstoqueUsaAIdadeDoProdutoQuandoEleEMaisNovoQueAJanela(): void
    {
        // Cadastrado há 10 dias, vendeu 10: 1 por dia. 20 em estoque duram 20 dias.
        $l = [
            'id' => 1, 'name' => 'Novo', 'stock_quantity' => 20, 'min_stock' => 0, 'cost_price' => 10,
            'cmv' => 100, 'quantidade_janela' => 10, 'created_at' => '2026-10-27 12:00:00',
        ];
        $item = AnalyzingBIService::montarEstoque([$l], $this->agora())[0];

        $this->assertSame(20, $item['cobertura_dias']);
        $this->assertSame('2026-11-26', $item['repor_em']);
        $this->assertSame(200.0, $item['capital']);
        $this->assertSame(0.5, $item['giro']);
    }

    public function testVendaAnteriorAoCadastroContaNaIdadeDoProduto(): void
    {
        // Cadastrado hoje, com histórico importado desde 90+ dias: 180 em 90 dias
        // = 2 por dia; 30 em estoque duram 15 dias, não 0.
        $l = [
            'id' => 1, 'name' => 'Importado', 'stock_quantity' => 30, 'min_stock' => 0, 'cost_price' => 10,
            'cmv' => 1800, 'quantidade_janela' => 180, 'created_at' => '2026-11-06 11:00:00',
            'primeira_venda' => '2026-01-10 09:00:00',
        ];

        $this->assertSame(15, AnalyzingBIService::montarEstoque([$l], $this->agora())[0]['cobertura_dias']);
    }

    public function testCuponsSeparamOsDoisGrupos(): void
    {
        $r = AnalyzingBIService::montarCupons([
            ['coupon_code' => null, 'pedidos' => 10, 'desconto' => 0, 'receita' => 1000, 'lucro' => 400],
            ['coupon_code' => 'A10', 'pedidos' => 2, 'desconto' => 20, 'receita' => 180, 'lucro' => 45],
            ['coupon_code' => 'B20', 'pedidos' => 2, 'desconto' => 40, 'receita' => 160, 'lucro' => 20],
        ]);

        $this->assertSame(['B20', 'A10'], array_column($r['cupons'], 'codigo'), 'maior desconto primeiro');
        // Com cupom: receita 340, lucro 65, ticket 85, margem 19,12%.
        $this->assertSame(60.0, $r['com_cupom']['desconto']);
        $this->assertSame(85.0, $r['com_cupom']['ticket_medio']);
        $this->assertSame(19.12, $r['com_cupom']['margem']);
        $this->assertSame(100.0, $r['sem_cupom']['ticket_medio']);
        $this->assertSame(40.0, $r['sem_cupom']['margem']);
    }

    public function testClienteSumidoDepoisDeSessentaDias(): void
    {
        $linha = static fn (int $id, string $nome, float $total, string $ultima): array => [
            'id' => $id, 'name' => $nome, 'email' => $id . '@x.test', 'pedidos' => 2, 'total' => $total, 'ultima_compra' => $ultima,
        ];
        $lista = CustomerReport::montar([
            $linha(1, 'Recente', 100, '2026-09-08 12:00:00'), // 59 dias
            $linha(2, 'Sumido', 500, '2026-09-07 12:00:00'),  // 60 dias
        ], $this->agora());

        $this->assertSame([2, 1], array_column($lista, 'id'), 'quem compra mais vem primeiro');
        $this->assertTrue($lista[0]['sumido'], 'com 60 dias já conta como sumido');
        $this->assertFalse($lista[1]['sumido'], 'com 59 dias ainda não');
        $this->assertSame(250.0, $lista[0]['ticket_medio']);
    }

    public function testCodigoDeBarrasNormaliza(): void
    {
        $this->assertSame('7891234567895', Barcode::normalize(' 7891234567895 '));
        $this->assertSame('CAM-AZUL.M', Barcode::normalize('cam-azul.m'));
        $this->assertNull(Barcode::normalize('   '), 'vazio apaga o código');
    }

    public function testCodigoDeBarrasRecusaCaracteresInvalidos(): void
    {
        $this->expectException(\InvalidArgumentException::class, fn () => Barcode::normalize('abc 123'));
        $this->expectException(\InvalidArgumentException::class, fn () => Barcode::normalize(str_repeat('1', 41)));
    }
}
