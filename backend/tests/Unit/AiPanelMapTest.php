<?php

namespace Tests\Unit;

use App\Services\AiPanelMap;
use App\Services\AiStoreData;
use Tests\TestCase;

/**
 * Mapa do painel e consultas do assistente de IA: o que a IA recebe para
 * guiar a pessoa pelo sistema e responder sobre a loja.
 */
final class AiPanelMapTest extends TestCase
{
    public function testGerenteRecebeLinkDeTodasAsPaginas(): void
    {
        $paginas = AiPanelMap::paginas('teste', 71, static fn (): bool => true);
        $links = AiPanelMap::links($paginas);
        $this->assertTrue(count($links) >= 16);
        foreach ($links as $u) {
            $this->assertTrue(
                strpos($u, '/painel/teste') !== false || strpos($u, '/loja/teste') !== false,
                "link fora da loja: {$u}"
            );
        }
        $texto = AiPanelMap::texto($paginas);
        $this->assertStringContainsString('configuracoes#config-store-photo-section', $texto);
        $this->assertStringContainsString('Foto da loja', $texto);
    }

    public function testFuncionarioNaoRecebeLinkDePaginaDoGerente(): void
    {
        $soFuncionario = ['store.panel.view', 'store.catalog.read', 'store.orders.read', 'store.pdv.operate', 'store.goals.read', 'store.roles.read'];
        $paginas = AiPanelMap::paginas('teste', 71, static fn (string $p): bool => in_array($p, $soFuncionario, true));
        $links = implode("\n", AiPanelMap::links($paginas));
        $this->assertStringNotContainsString('configuracoes', $links);
        $this->assertStringNotContainsString('analyzing-bi', $links);
        $this->assertStringContainsString('/painel/teste/pdv', $links);

        $texto = AiPanelMap::texto($paginas);
        $this->assertStringContainsString('Configurações — SÓ O GERENTE ACESSA', $texto);
        // A âncora de página sem acesso não vira link.
        $this->assertStringNotContainsString('#config-store-photo-section', $texto);
    }

    public function testPerguntasSobreOBiTrazemOGlossario(): void
    {
        foreach (['oque é isso que aparece no meu BI TIR', 'O que significa markup?', 'Como leio a curva ABC?', 'Meu VPL deu negativo, e agora?', 'o que é a linha tracejada do gráfico?'] as $p) {
            $this->assertTrue(\App\Services\AiBiContext::ehPerguntaDoBi($p), $p);
        }
        $this->assertFalse(\App\Services\AiBiContext::ehPerguntaDoBi('Dê três dicas para melhorar o atendimento.'));
        $g = \App\Services\AiBiContext::textoGlossario();
        // O que faltou na resposta real: a TIR existe e fica no simulador.
        $this->assertStringContainsString('TIR (taxa interna de retorno) — onde aparece: cartão "TIR" do simulador', $g);
        $this->assertStringContainsString('Curva ABC', $g);
    }

    public function testProdutoCitadoPeloNomeOuPorPalavrasMarcantes(): void
    {
        $nomes = [1 => 'Tênis de Corrida Masculino', 2 => 'Tênis de Basquete Air Dunk High', 3 => 'Legging Yoga Compressão', 4 => 'Boné'];
        $this->assertSame([1], AiStoreData::produtosNaPergunta('Quanto tenho do tenis de corrida masculino?', $nomes));
        $this->assertSame([2], AiStoreData::produtosNaPergunta('E o basquete air dunk, tem o 44?', $nomes));
        $this->assertSame([3], AiStoreData::produtosNaPergunta('A legging de yoga vende bem?', $nomes));
        $this->assertSame([4], AiStoreData::produtosNaPergunta('Quantos bonés... digo, quanto tem de boné?', $nomes));
        // Só "tênis" é pouco para escolher um produto.
        $this->assertSame([], AiStoreData::produtosNaPergunta('Dicas para vender mais tênis', $nomes));
    }
}
