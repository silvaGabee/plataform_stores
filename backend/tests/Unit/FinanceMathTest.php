<?php

namespace Tests\Unit;

use App\Domain\Finance\Installments;
use App\Domain\Finance\Money;
use App\Domain\Finance\Trend;
use App\Services\AnalyzingBIService;
use Tests\TestCase;

/**
 * Fórmulas financeiras do BI, das metas e do PDV.
 *
 * Os valores esperados foram calculados à mão (teste de mesa), não copiados
 * da saída do código — senão o teste só confirmaria o que o código já faz.
 */
final class FinanceMathTest extends TestCase
{
    /** Compara reais com tolerância de meio centavo. */
    private function assertMoney(float $esperado, float $real, string $msg = ''): void
    {
        if (abs($esperado - $real) > 0.005) {
            throw new \RuntimeException(($msg !== '' ? $msg . ' — ' : '') . "esperado {$esperado}, obtido {$real}");
        }
    }

    private function assertNear(float $esperado, float $real, float $tol, string $msg = ''): void
    {
        if (abs($esperado - $real) > $tol) {
            throw new \RuntimeException(($msg !== '' ? $msg . ' — ' : '') . "esperado {$esperado} ± {$tol}, obtido {$real}");
        }
    }

    // ───────────────────────────── rateio (Money::splitEvenly)

    public function testRateioDeMilEntreTresFechaOTotal(): void
    {
        $partes = Money::splitEvenly(1000.00, 3);
        $this->assertSame([333.34, 333.33, 333.33], $partes);
        $this->assertSame(100000, array_sum(array_map([Money::class, 'toCents'], $partes)), 'a soma deve ser exatamente R$ 1.000,00');
    }

    public function testRateioExatoNaoGeraCentavoExtra(): void
    {
        $this->assertSame([25.0, 25.0, 25.0, 25.0], Money::splitEvenly(100.00, 4));
    }

    public function testRateioMenorQueUmCentavoPorParte(): void
    {
        // 2 centavos entre 3: duas partes de 1 centavo e uma de zero.
        $this->assertSame([0.01, 0.01, 0.0], Money::splitEvenly(0.02, 3));
    }

    public function testRateioPartesNuncaDiferemMaisDeUmCentavo(): void
    {
        foreach ([[999.99, 7], [1234.56, 11], [0.10, 3], [50000.00, 13]] as [$total, $n]) {
            $cent = array_map([Money::class, 'toCents'], Money::splitEvenly($total, $n));
            $this->assertSame(Money::toCents($total), array_sum($cent), "soma de {$total} em {$n}");
            $this->assertTrue(max($cent) - min($cent) <= 1, "diferença máxima de 1 centavo em {$total}/{$n}");
        }
    }

    public function testRateioRecusaZeroPartes(): void
    {
        $this->expectException(\InvalidArgumentException::class, fn () => Money::splitEvenly(100, 0));
    }

    // ───────────────────────────── tendência (Trend)

    public function testRegressaoDeRetaPerfeita(): void
    {
        // y = 2 + 2x
        $r = Trend::linearRegression([2, 4, 6, 8]);
        $this->assertNear(2.0, $r['a'], 1e-9, 'intercepto');
        $this->assertNear(2.0, $r['b'], 1e-9, 'inclinação');
        $this->assertNear(1.0, $r['r2'], 1e-9, 'R²');
    }

    public function testRegressaoTesteDeMesa(): void
    {
        // x = 0..4, y = [1, 3, 2, 5, 4]
        // x̄ = 2, ȳ = 3, Σ(x−x̄)(y−ȳ) = 8, Σ(x−x̄)² = 10
        // b = 0,8 ; a = 3 − 0,8·2 = 1,4
        // SQres = 3,6 ; SQtot = 10 ; R² = 1 − 3,6/10 = 0,64
        $r = Trend::linearRegression([1, 3, 2, 5, 4]);
        $this->assertNear(1.4, $r['a'], 1e-9, 'a');
        $this->assertNear(0.8, $r['b'], 1e-9, 'b');
        $this->assertNear(0.64, $r['r2'], 1e-9, 'R²');
    }

    public function testRegressaoSerieConstante(): void
    {
        $r = Trend::linearRegression([50, 50, 50]);
        $this->assertNear(0.0, $r['b'], 1e-9, 'sem inclinação');
        $this->assertNear(50.0, $r['a'], 1e-9, 'nível');
        $this->assertNear(1.0, $r['r2'], 1e-9, 'reta horizontal explica a série inteira');
    }

    public function testRegressaoExigeTresPontos(): void
    {
        $this->assertNull(Trend::linearRegression([10, 20]));
        $this->assertSame([], Trend::forecast([10, 20], 3));
    }

    public function testProjecaoContinuaAReta(): void
    {
        // y = 2 + 2x, x = 4 e 5 → 10 e 12
        $this->assertSame([10.0, 12.0], Trend::forecast([2, 4, 6, 8], 2));
    }

    public function testProjecaoNaoFicaNegativa(): void
    {
        // Queda de 5 por período: x = 3 daria −5; faturamento não é negativo.
        $this->assertSame([0.0, 0.0], Trend::forecast([10, 5, 0], 2));
    }

    // ───────────────────────────── parcelamento (tabela Price)

    public function testPriceParcelaDeTextoClassico(): void
    {
        // P = 1000, i = 2% a.m., n = 12
        // coef = 0,02 / (1 − 1,02^−12) = 0,0945596 → PMT = R$ 94,56
        $r = Installments::price(1000, 2, 12);
        $this->assertMoney(94.56, $r['parcela'], 'PMT');
        $this->assertNear(0.0945596, $r['coeficiente'], 1e-6, 'coeficiente');
        $this->assertMoney(20.00, $r['tabela'][0]['juros'], 'juros do 1º mês = 1000 × 2%');
        $this->assertMoney(74.56, $r['tabela'][0]['amortizacao'], 'amortização do 1º mês');
        $this->assertMoney(925.44, $r['tabela'][0]['saldo'], 'saldo após o 1º mês');
    }

    public function testPriceTabelaFecha(): void
    {
        $r = Installments::price(1000, 2, 12);
        $amort = 0;
        $parcelas = 0;
        foreach ($r['tabela'] as $l) {
            $amort += Money::toCents($l['amortizacao']);
            $parcelas += Money::toCents($l['parcela']);
        }
        $this->assertSame(100000, $amort, 'Σ amortizações = principal');
        $this->assertSame(0.0, (float) end($r['tabela'])['saldo'], 'saldo final zero');
        $this->assertSame(Money::toCents($r['total']), $parcelas, 'total = Σ parcelas');
        $this->assertMoney($r['total'] - 1000, $r['juros_total'], 'juros = total − principal');
    }

    public function testPriceJurosDecrescemEAmortizacaoCresce(): void
    {
        $t = Installments::price(5000, 3.5, 10)['tabela'];
        for ($k = 1; $k < count($t); $k++) {
            $this->assertTrue($t[$k]['juros'] <= $t[$k - 1]['juros'], "juros não crescem na parcela {$k}");
            $this->assertTrue($t[$k]['amortizacao'] >= $t[$k - 1]['amortizacao'] - 0.01, "amortização não cai na parcela {$k}");
        }
    }

    public function testPriceSemJurosEhDivisaoExata(): void
    {
        $r = Installments::price(1000, 0, 3);
        $this->assertSame([333.34, 333.33, 333.33], array_column($r['tabela'], 'parcela'));
        $this->assertMoney(0.0, $r['juros_total']);
        $this->assertMoney(1000.0, $r['total']);
    }

    public function testPriceUmaParcelaEhJurosSimplesDeUmPeriodo(): void
    {
        // n = 1: PMT = P(1 + i) = 1000 × 1,02
        $this->assertMoney(1020.00, Installments::price(1000, 2, 1)['parcela']);
    }

    public function testTaxaAnualEquivalenteComposta(): void
    {
        // (1,01)^12 − 1 = 12,6825% (não 12%: juros compostos)
        $this->assertNear(12.6825, Installments::price(100, 1, 2)['taxa_anual_equivalente'], 0.0001);
    }

    public function testPriceRecusaEntradaInvalida(): void
    {
        $this->expectException(\InvalidArgumentException::class, fn () => Installments::price(0, 2, 3));
        $this->expectException(\InvalidArgumentException::class, fn () => Installments::price(100, -1, 3));
        $this->expectException(\InvalidArgumentException::class, fn () => Installments::price(100, 2, 0));
        $this->expectException(\InvalidArgumentException::class, fn () => Installments::price(100, 2, 49));
    }

    // ───────────────────────────── variação percentual do BI

    public function testVariacaoPercentual(): void
    {
        $this->assertNear(25.0, AnalyzingBIService::growthPercent(125, 100), 1e-9, 'alta');
        $this->assertNear(-40.0, AnalyzingBIService::growthPercent(60, 100), 1e-9, 'queda');
        $this->assertNear(0.0, AnalyzingBIService::growthPercent(100, 100), 1e-9, 'estável');
    }

    public function testVariacaoDeProdutoSemBaseEhNula(): void
    {
        // Card de produto: 1 unidade contra 0 não é "+100%" — não há base.
        $this->assertNull(AnalyzingBIService::productGrowth(1, 0));
        $this->assertSame(-50.0, AnalyzingBIService::productGrowth(2, 4));
        $this->assertSame(50.0, AnalyzingBIService::productGrowth(6, 4));
    }

    public function testVariacaoPercentualComBaseZero(): void
    {
        // Sem base de comparação: convenção do sistema é 100% se surgiu venda, 0% se não.
        $this->assertNear(100.0, AnalyzingBIService::growthPercent(30, 0), 1e-9);
        $this->assertNear(0.0, AnalyzingBIService::growthPercent(0, 0), 1e-9);
    }
}
