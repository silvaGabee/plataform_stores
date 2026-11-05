<?php

namespace Tests\Unit;

use App\Domain\Finance\CashDiscount;
use App\Services\OrderPricing;
use App\Services\ShippingQuote;
use Tests\TestCase;

/**
 * Desconto à vista no PIX, frete por CEP e a taxa equivalente do desconto.
 */
final class VitrineExtrasTest extends TestCase
{
    // ───────────────────────────── desconto no PIX

    public function testPrecoNoPixAplicaOPercentualDaLoja(): void
    {
        // 5% de R$ 39,90 = R$ 1,995 → R$ 2,00 (meio centavo arredonda para cima).
        $this->assertSame(37.9, OrderPricing::pixPrice(['pix_discount_pct' => 5], 39.90));
        $this->assertSame(2.0, OrderPricing::pixDiscount(39.90, 5));
        $this->assertNull(OrderPricing::pixPrice(['pix_discount_pct' => 0], 39.90));
        $this->assertNull(OrderPricing::pixPrice([], 39.90));
    }

    public function testPercentualAcimaDoTetoELimitado(): void
    {
        $this->assertSame(OrderPricing::PIX_MAX_PCT, OrderPricing::pixPct(['pix_discount_pct' => 80]));
        $this->assertSame(0.0, OrderPricing::pixPct(['pix_discount_pct' => -3]));
    }

    public function testPixIncideDepoisDoCupomENaoNoFrete(): void
    {
        // Itens 200, cupom 20, frete 15, PIX 5%: desconto PIX = 5% de 180 = 9.
        $b = OrderPricing::breakdown(200.0, 20.0, 15.0, 5.0, true);
        $this->assertSame(9.0, $b['pix_discount']);
        $this->assertSame(29.0, $b['discount']);
        $this->assertSame(186.0, $b['total']);

        $cartao = OrderPricing::breakdown(200.0, 20.0, 15.0, 5.0, false);
        $this->assertSame(0.0, $cartao['pix_discount']);
        $this->assertSame(195.0, $cartao['total']);
    }

    // ───────────────────────────── frete por CEP

    private function regras(): array
    {
        return [
            ['name' => 'Minas Gerais', 'cep_start' => '30000000', 'cep_end' => '39999999', 'price' => 19.90, 'days' => 5],
            ['name' => 'Belo Horizonte', 'cep_start' => '30000000', 'cep_end' => '31999999', 'price' => 9.90, 'days' => 2],
        ];
    }

    public function testFaixaMaisEstreitaVence(): void
    {
        $q = ShippingQuote::quote($this->regras(), null, '30140-071', 100);
        $this->assertTrue($q['disponivel']);
        $this->assertSame('Belo Horizonte', $q['regra']);
        $this->assertSame(9.9, $q['valor']);
        $this->assertSame(2, $q['prazo']);

        $interior = ShippingQuote::quote($this->regras(), null, '38400000', 100);
        $this->assertSame('Minas Gerais', $interior['regra']);
    }

    public function testCepForaDasFaixasNaoEntrega(): void
    {
        $q = ShippingQuote::quote($this->regras(), null, '01310-100', 100);
        $this->assertFalse($q['disponivel']);
        $this->assertFalse(ShippingQuote::quote($this->regras(), null, '123', 100)['disponivel']);
    }

    public function testFreteGratisAcimaDoMinimo(): void
    {
        $q = ShippingQuote::quote($this->regras(), 199.0, '30140071', 199.0);
        $this->assertTrue($q['gratis']);
        $this->assertSame(0.0, $q['valor']);
        $this->assertFalse(ShippingQuote::quote($this->regras(), 199.0, '30140071', 198.99)['gratis']);
    }

    public function testLojaSemFaixasEntregaSemCusto(): void
    {
        $q = ShippingQuote::quote([], null, null, 50);
        $this->assertTrue($q['disponivel']);
        $this->assertTrue($q['sem_regras']);
        $this->assertSame(0.0, $q['valor']);
    }

    // ───────────────────────────── taxa equivalente do desconto à vista

    public function testDescontoContraUmaParcelaTemFormulaFechada(): void
    {
        // 5% à vista contra 1x em 30 dias: i = 0,05 / 0,95 = 5,2632% a.m.
        $this->assertSame(5.2632, CashDiscount::taxaEquivalente(5, 1));
        $this->assertSame(0.0, CashDiscount::taxaEquivalente(0, 3));
    }

    public function testTaxaEquivalenteFechaAEquacaoDeValorPresente(): void
    {
        // 5% à vista contra 3x sem juros: a taxa achada precisa zerar
        // (1 − d) − a(n, i)/n.
        $i = CashDiscount::taxaEquivalente(5, 3);
        $this->assertTrue($i > 2.5 && $i < 2.7, 'esperado ≈ 2,6% a.m., veio ' . $i);
        $this->assertTrue(abs(0.95 - CashDiscount::fatorMedio($i / 100, 3)) < 0.00001, 'equação de valor presente não fechou');
    }

    public function testMaisParcelasDiluemOCustoDoDesconto(): void
    {
        $this->assertTrue(CashDiscount::taxaEquivalente(5, 6) < CashDiscount::taxaEquivalente(5, 3));
    }
}
