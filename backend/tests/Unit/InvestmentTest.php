<?php

namespace Tests\Unit;

use App\Domain\Finance\Installments;
use App\Domain\Finance\Investment;
use App\Services\AnalyzingBIService;
use Tests\TestCase;

/**
 * VPL, TIR, payback, fluxo de uma compra de estoque e Price × SAC.
 * Os valores esperados foram calculados à mão (conta no comentário).
 */
final class InvestmentTest extends TestCase
{
    // ------------------------------------------------------------ VPL

    public function testVplZeroQuandoATaxaEhARentabilidade(): void
    {
        // −1000 hoje e 1100 daqui a um período: rende exatamente 10%.
        $this->assertSame(0.0, Investment::npv(10, [-1000, 1100]));
    }

    public function testVplDeUmaSerieUniforme(): void
    {
        // 500 · (1/1,1 + 1/1,21 + 1/1,331) = 500 · 2,486852 = 1243,43
        $this->assertSame(243.43, Investment::npv(10, [-1000, 500, 500, 500]));
        // Com taxa zero o VPL é a soma simples: o lucro nominal.
        $this->assertSame(500.0, Investment::npv(0, [-1000, 500, 500, 500]));
    }

    public function testVplNegativoQuandoATmaEhMaiorQueORetorno(): void
    {
        // A TIR desse fluxo é 10%; a 12% a.m. não compensa.
        $this->assertTrue(Investment::npv(12, [-1000, 1100]) < 0);
    }

    // ------------------------------------------------------------ TIR

    public function testTirDeUmPeriodo(): void
    {
        $this->assertEquals(10.0, Investment::irr([-1000, 1100]));
    }

    public function testTirDeDoisPeriodosPelaFormulaDeBhaskara(): void
    {
        // 60x + 60x² = 100, com x = 1/(1+r)  →  x = (−1 + √(1 + 20/3)) / 2
        // x = 0,884437  →  r = 1/x − 1 = 13,0662%
        $tir = Investment::irr([-100, 60, 60]);
        $this->assertTrue(abs($tir - 13.0662) < 0.001, "TIR {$tir}");
    }

    public function testNoPontoDaTirOVplZera(): void
    {
        $fluxos = [-5000, 1200, 1500, 1800, 2000];
        $this->assertSame(0.0, Investment::npv((float) Investment::irr($fluxos), $fluxos));
    }

    public function testSemSaidaOuSemEntradaNaoHaTir(): void
    {
        $this->assertNull(Investment::irr([100, 200]));
        $this->assertNull(Investment::irr([-100, 0, 0]));
    }

    public function testTirNegativaQuandoNaoRecuperaOInvestido(): void
    {
        // −1000 e só 900 de volta: perde 10%.
        $this->assertEquals(-10.0, Investment::irr([-1000, 900]));
    }

    // ------------------------------------------------------------ payback

    public function testPaybackSimplesInterpolaDentroDoMes(): void
    {
        // Acumulado: −600, −200, +200. Faltavam 200 de 400 no 3º mês: 2,5.
        $this->assertSame(2.5, Investment::payback([-1000, 400, 400, 400]));
    }

    public function testPaybackDescontadoPodeNuncaChegar(): void
    {
        // A 10%: 363,64 + 330,58 + 300,53 = 994,75 < 1000. Sem desconto se
        // pagava em 2,5 meses; com o valor do dinheiro no tempo, não se paga.
        $this->assertNull(Investment::payback([-1000, 400, 400, 400], 10));
    }

    // ------------------------------------------------------------ compra de estoque

    public function testCompraVendeNoRitmoDoHistorico(): void
    {
        // 10 peças a R$ 50, vendendo 4 por mês a R$ 100: 4, 4 e 2.
        $r = Investment::stockPurchaseFlows(10, 50, 100, 4);
        $this->assertSame([-500.0, 400.0, 400.0, 200.0], $r['fluxos']);
        $this->assertSame(3, $r['meses']);
        $this->assertSame(0.0, $r['sobra']);
        $this->assertSame(1, $r['meses_ate_comecar']);
    }

    public function testEstoqueAtualVendePrimeiro(): void
    {
        // Ainda há 6 na prateleira. Lote novo vendido até o fim de cada mês:
        // S(1) = máx(4 − 6, 0) = 0; S(2) = 2; S(3) = 6; S(4) = 10.
        $r = Investment::stockPurchaseFlows(10, 50, 100, 4, 6);
        $this->assertSame([-500.0, 0.0, 200.0, 400.0, 400.0], $r['fluxos']);
        $this->assertSame(2, $r['meses_ate_comecar']);
        // O mesmo lote, comprado cedo, vale menos hoje.
        $this->assertTrue(Investment::npv(3, $r['fluxos']) < Investment::npv(3, [-500.0, 400.0, 400.0, 200.0]));
    }

    public function testSemVendaNaoHaEntrada(): void
    {
        $r = Investment::stockPurchaseFlows(10, 50, 100, 0);
        $this->assertSame([-500.0], $r['fluxos']);
        $this->assertSame(10.0, $r['sobra']);
        $this->assertNull(Investment::irr($r['fluxos']));
    }

    public function testOQueNaoVendeNoHorizonteFicaDeFora(): void
    {
        $r = Investment::stockPurchaseFlows(100, 10, 20, 1);
        $this->assertSame(Investment::MAX_MESES, $r['meses']);
        $this->assertSame(64.0, $r['sobra']);
        $this->assertCount(Investment::MAX_MESES + 1, $r['fluxos']);
    }

    public function testCompraSemCustoEhRecusada(): void
    {
        $recusou = false;
        try {
            Investment::stockPurchaseFlows(10, 0, 100, 4);
        } catch (\InvalidArgumentException $e) {
            $recusou = true;
        }
        $this->assertTrue($recusou);
    }

    // ------------------------------------------------------------ Price × SAC

    public function testSacDeLivro(): void
    {
        // R$ 1.000 a 2% a.m. em 4: amortização 250; juros 20, 15, 10 e 5.
        $s = Installments::sac(1000, 2, 4);
        $this->assertSame([270.0, 265.0, 260.0, 255.0], array_column($s['tabela'], 'parcela'));
        $this->assertSame([20.0, 15.0, 10.0, 5.0], array_column($s['tabela'], 'juros'));
        $this->assertSame(250.0, $s['amortizacao']);
        $this->assertSame(1050.0, $s['total']);
        $this->assertSame(50.0, $s['juros_total']);
        $this->assertSame(0.0, $s['tabela'][3]['saldo']);
    }

    public function testSacFechaOCentavoQuandoADivisaoNaoEhExata(): void
    {
        // R$ 1.000 em 3: amortizações 333,34 + 333,33 + 333,33.
        $s = Installments::sac(1000, 1.5, 3);
        $this->assertSame(100000, (int) round(array_sum(array_column($s['tabela'], 'amortizacao')) * 100));
        $this->assertSame(0.0, $s['tabela'][2]['saldo']);
    }

    public function testSacPagaMenosJurosQueAPrice(): void
    {
        // Price: PMT = 1000 · 0,02 / (1 − 1,02⁻⁴) = 262,62. No livro, 4 × 262,62
        // = 1.050,48. Na fatura, cada juro é arredondado ao centavo (20,00;
        // 15,15; 10,20; 5,15) e a última parcela quita o saldo: 262,64.
        // Total 1.050,50 — mesmo assim, 50 centavos acima do SAC.
        $c = Installments::compare(1000, 2, 4);
        $this->assertSame(262.62, $c['price']['parcela']);
        $this->assertSame(262.64, $c['price']['tabela'][3]['parcela']);
        $this->assertSame(50.5, $c['price']['juros_total']);
        $this->assertSame(50.0, $c['sac']['juros_total']);
    }

    public function testPelaPropriaTaxaAsDuasValemOPrincipal(): void
    {
        // Juros menores no SAC não significam financiamento mais barato:
        // descontadas pela taxa do contrato, as duas séries valem R$ 1.000.
        $c = Installments::compare(1000, 2, 12, 2);
        $this->assertTrue(abs($c['valor_presente_price'] - 1000) <= 0.02, (string) $c['valor_presente_price']);
        $this->assertTrue(abs($c['valor_presente_sac'] - 1000) <= 0.02, (string) $c['valor_presente_sac']);
    }

    public function testComTmaMaiorQueATaxaAPriceValeMenosHoje(): void
    {
        // A loja rende 4% a.m. e o financiamento cobra 2%: pagar mais tarde
        // (Price) pesa menos a valor presente. Com TMA de 1%, o contrário.
        $alta = Installments::compare(1000, 2, 12, 4);
        $this->assertTrue($alta['valor_presente_price'] < $alta['valor_presente_sac']);
        $baixa = Installments::compare(1000, 2, 12, 1);
        $this->assertTrue($baixa['valor_presente_price'] > $baixa['valor_presente_sac']);
    }

    // ------------------------------------------------------------ simulador do BI

    public function testPrecoDoSimuladorEhOMedioRecebidoNaoOEtiqueta(): void
    {
        $agora = new \DateTimeImmutable('2026-11-09 12:00:00');
        $linha = [
            'id' => 7, 'name' => 'Tênis', 'stock_quantity' => 5, 'min_stock' => 2, 'cost_price' => '120.00',
            'sale_price' => '299.90', 'promo_price' => null, 'created_at' => '2025-01-01 00:00:00',
            // 12 vendidos por 3.000 (com descontos): 250 por peça. 9 em 90 dias: 3/mês.
            'quantidade' => '12', 'receita' => '3000.00', 'cmv' => '1440', 'quantidade_janela' => '9', 'primeira_venda' => '2025-02-01',
        ];
        $novo = ['id' => 8, 'name' => 'Boné', 'quantidade' => '0', 'receita' => '0', 'quantidade_janela' => '0',
            'sale_price' => '80.00', 'promo_price' => '59.90', 'created_at' => '2026-11-01 00:00:00', 'primeira_venda' => null] + $linha;
        $o = AnalyzingBIService::montarOpcoesCompra([$linha, $novo], $agora);

        $this->assertSame('Boné', $o[0]['nome']);
        $this->assertSame(59.9, $o[0]['preco']);
        $this->assertSame('cadastro', $o[0]['preco_origem']);
        $this->assertSame(250.0, $o[1]['preco']);
        $this->assertSame('media_vendida', $o[1]['preco_origem']);
        $this->assertSame(3.0, $o[1]['venda_mensal']);
    }

    public function testAnaliseDaCompraJuntaVplTirEDecisao(): void
    {
        // 10 peças a R$ 50, 4/mês a R$ 100, sem estoque antes: −500, 400, 400, 200.
        $p = ['custo' => 50.0, 'preco' => 100.0, 'venda_mensal' => 4.0, 'estoque' => 0];
        $a = AnalyzingBIService::analisarCompra($p, 10, 2, true, 1.5, 6);

        $this->assertSame(500.0, $a['investimento']);
        $this->assertSame(500.0, $a['lucro_nominal']);
        $this->assertSame(Investment::npv(2, [-500, 400, 400, 200]), $a['vpl']);
        $this->assertSame('compensa', $a['decisao']);
        $this->assertTrue($a['tir'] > 2);
        // Financia exatamente o investimento.
        $this->assertSame(500.0, $a['financiamento']['price']['principal']);
        $this->assertSame(6, $a['financiamento']['sac']['parcelas']);
    }

    public function testVplETirConcordamNaDecisao(): void
    {
        // Margem de 2%, vendendo devagar: não paga uma TMA de 3% a.m.
        $p = ['custo' => 98.0, 'preco' => 100.0, 'venda_mensal' => 1.0, 'estoque' => 0];
        $a = AnalyzingBIService::analisarCompra($p, 6, 3, false);
        $this->assertSame('nao_compensa', $a['decisao']);
        $this->assertTrue($a['tir'] < 3);
        $this->assertNull($a['financiamento']);
    }
}
