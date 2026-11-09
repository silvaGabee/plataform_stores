<?php

namespace Tests\Unit;

use App\Domain\Goals\TeamGoal;
use Tests\TestCase;

/**
 * Contas da tela de Metas: de onde veio o vendido e quanto sugerir dividir.
 */
final class TeamGoalTest extends TestCase
{
    public function testAsTresOrigensSomamOTotalDaLoja(): void
    {
        // 7 e 9 são funcionários; 1 é o gerente. A loja vendeu 1.000,00.
        $o = TeamGoal::salesBySource([7 => 300.10, 9 => 199.90, 1 => 150.00], [7, 9], 1000.00);

        $this->assertSame(500.0, (float) $o['employees']);
        $this->assertSame(150.0, (float) $o['managers']);
        $this->assertSame(350.0, (float) $o['others']);
        $this->assertSame(100000, (int) round(($o['employees'] + $o['managers'] + $o['others']) * 100));
    }

    public function testCentavosQuebradosNaoSobramNemFaltam(): void
    {
        // Em float, 0,1 + 0,2 − 0,3 dá 5,55e-17: "outras" sairia -0,00 ou 0,01.
        $o = TeamGoal::salesBySource([7 => 0.1, 9 => 0.2], [7, 9], 0.3);

        $this->assertSame(0.3, (float) $o['employees']);
        $this->assertSame(0.0, (float) $o['others']);
    }

    public function testSoVitrineFicaTudoEmOutras(): void
    {
        $o = TeamGoal::salesBySource([7 => 0.0, 1 => 0.0], [7], 820.45);

        $this->assertSame(0.0, (float) $o['employees']);
        $this->assertSame(0.0, (float) $o['managers']);
        $this->assertSame(820.45, (float) $o['others']);
    }

    public function testQuemNaoEhFuncionarioContaComoGerente(): void
    {
        // Sem funcionários na equipe, toda venda de PDV é do gerente.
        $o = TeamGoal::salesBySource([1 => 400.0], [], 400.0);

        $this->assertSame(400.0, (float) $o['managers']);
        $this->assertSame(0.0, (float) $o['others']);
    }

    public function testHistoricoSaoOsMesesCompletosAntesDaMeta(): void
    {
        $this->assertSame(['2026-08-01', '2026-10-31'], TeamGoal::historyRange('2026-11-01', 3));
        // Virada de ano e fevereiro curto.
        $this->assertSame(['2025-12-01', '2026-02-28'], TeamGoal::historyRange('2026-03-01', 3));
        $this->assertSame(['2023-12-01', '2024-02-29'], TeamGoal::historyRange('2024-03-01', 3));
    }

    public function testSugestaoEhMetaVezesParticipacao(): void
    {
        // Funcionários fizeram 62,5% das vendas: de 20.000,00, sugere 12.500,00.
        $this->assertSame(12500.0, TeamGoal::suggestedSplit(20000.0, 62.5));
        // Arredonda no centavo: 1.000,00 × 33,3% = 333,00.
        $this->assertSame(333.0, TeamGoal::suggestedSplit(1000.0, 33.3));
        $this->assertSame(0.33, TeamGoal::suggestedSplit(1.0, 33.3));
    }

    public function testSugestaoNuncaPassaDaMeta(): void
    {
        // O servidor recusa dividir mais que a meta; a sugestão não pode pedir isso.
        $this->assertSame(5000.0, TeamGoal::suggestedSplit(5000.0, 104.2));
    }

    public function testSemHistoricoOuSemMetaNaoHaSugestao(): void
    {
        $this->assertNull(TeamGoal::suggestedSplit(5000.0, null));
        $this->assertNull(TeamGoal::suggestedSplit(0.0, 50.0));
        $this->assertSame(0.0, TeamGoal::suggestedSplit(5000.0, 0.0));
    }
}
