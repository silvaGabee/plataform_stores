<?php

namespace Tests\Unit;

use App\Services\InstallmentPlan;
use App\Services\ProductService;
use Tests\TestCase;

/**
 * Parcelamento da vitrine e regras de preço.
 *
 * Até `installments_free` o valor é só dividido; acima, a parcela segue a
 * tabela Price com a taxa mensal da loja.
 */
final class InstallmentPlanTest extends TestCase
{
    private function loja(array $s = []): array
    {
        return $s + ['installments_max' => 6, 'installments_free' => 3, 'installments_rate' => 1.99, 'installments_min_value' => 20];
    }

    public function testSemJurosAteOLimiteDaLoja(): void
    {
        $q = InstallmentPlan::quote($this->loja(), 300.0, 3);
        $this->assertTrue($q['sem_juros']);
        $this->assertSame(100.0, $q['parcela']);
        $this->assertSame(300.0, $q['total']);
        $this->assertSame(0.0, $q['juros']);
    }

    public function testAcimaDoLimiteUsaTabelaPrice(): void
    {
        // PMT = 300 × 0,0199 / (1 − 1,0199^−4) = 300 × 0,26256 = 78,77
        $q = InstallmentPlan::quote($this->loja(), 300.0, 4);
        $this->assertFalse($q['sem_juros']);
        $this->assertSame(78.77, $q['parcela']);
        $this->assertTrue($q['juros'] > 0, 'deveria haver juros');
        $this->assertEquals(round($q['total'] - 300, 2), $q['juros']);
    }

    public function testOpcoesParamNaParcelaMinima(): void
    {
        // R$ 50 com parcela mínima de R$ 20: só 1x e 2x.
        $ops = InstallmentPlan::options($this->loja(), 50.0);
        $this->assertCount(2, $ops);
        $this->assertSame(2, $ops[1]['parcelas']);
    }

    public function testChamadaPeloTextoDaVitrine(): void
    {
        $this->assertSame('ou 3x de R$ 39,97 sem juros', InstallmentPlan::headline($this->loja(), 119.90));
        $this->assertNull(InstallmentPlan::headline($this->loja(['installments_free' => 1]), 119.90));
    }

    public function testPrecoNegativoERecusado(): void
    {
        $erro = null;
        try {
            ProductService::validarPrecos(['sale_price' => -50, 'cost_price' => 0], null);
        } catch (\InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
        $this->assertTrue($erro !== null, 'preço negativo deveria ser recusado');
    }

    public function testPromocaoPrecisaSerMenorQueOPreco(): void
    {
        $erro = null;
        try {
            ProductService::validarPrecos(['promo_price' => 200], ['sale_price' => 150, 'promo_price' => null]);
        } catch (\InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
        $this->assertTrue($erro !== null, 'promoção acima do preço deveria ser recusada');
        $ok = ProductService::validarPrecos(['promo_price' => '99.9'], ['sale_price' => 150, 'promo_price' => null]);
        $this->assertSame(99.9, $ok['promo_price']);
        $this->assertSame(99.9, product_effective_price(['sale_price' => 150, 'promo_price' => 99.9]));
        $this->assertSame(33, product_discount_percent(['sale_price' => 150, 'promo_price' => 99.9]));
    }
}
