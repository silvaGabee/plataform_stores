<?php

namespace App\Services;

/**
 * O que o assistente de IA recebe para ler o Analyzing BI com a pessoa.
 *
 *  - glossário: os textos do botão "!" de cada gráfico e os termos que
 *    aparecem dentro deles (TIR, VPL, markup…), com onde cada um aparece —
 *    a mesma fonte da tela (config/bi_ajuda.php). Vai para qualquer cargo:
 *    explicar um conceito não expõe dado da loja;
 *  - números: os valores atuais do BI desta loja, só para quem pode ver o BI
 *    (gerente). Saem de AnalyzingBIService, a mesma conta da tela.
 *
 * Antes a IA só tinha a lista de seções do mapa do painel e, perguntada sobre
 * a TIR, respondeu que "não via menção a TIR" no BI.
 */
final class AiBiContext
{
    private const TERMOS_DO_BI = '/\b(bi|analyzing|grafic\w*|indicador\w*|tir|vpl|tma|payback|price|sac|margem|markup|giro|cobertura|curva|abc|classe [abc]|ticket|cmv|capital|parad\w*|critic\w*|previs\w*|tendencia|tracejad\w*|simulador|vale a pena comprar|taxa equivalente|lucro|faturamento|cupons?|meta|ideias? de investimento|despesas sobre|pix|parcelamento|parcelad\w*|juros|compensa\w*|rentab\w*|rende|rendendo)\b/';

    /** A pergunta é sobre o BI ou sobre um termo dele? */
    public static function ehPerguntaDoBi(string $pergunta): bool
    {
        return preg_match(self::TERMOS_DO_BI, AiGuard::normalizar($pergunta)) === 1;
    }

    /** Glossário do BI em texto, para o prompt. */
    public static function textoGlossario(): string
    {
        $cfg = require PLATAFORM_BACKEND . '/config/bi_ajuda.php';
        $l = ['GLOSSÁRIO DO ANALYZING BI (o que cada parte da tela é e como se lê)'];
        $l[] = 'Grupos da página, na ordem: Visão geral, Vendas, Produtos, Pagamentos e cupons, Estoque, Decisões. Cada indicador tem um botão "!" com a explicação.';
        $l[] = '';
        $l[] = 'Termos que aparecem dentro dos gráficos:';
        foreach ($cfg['termos'] as [$termo, $onde, $oQue, $comoLer]) {
            $l[] = '- ' . $termo . ' — onde aparece: ' . $onde . '. O que é: ' . $oQue . ' Como ler: ' . $comoLer;
        }
        $l[] = '';
        $l[] = 'Seções e cartões:';
        foreach ($cfg['secoes'] as [$titulo, $oQue, $serve, $como]) {
            $l[] = '- ' . $titulo . ': ' . $oQue . ' Serve para: ' . $serve . ' Como funciona: ' . $como;
        }

        return implode("\n", $l);
    }

    /** Valores atuais do BI desta loja (só chamar para quem pode ver o BI). */
    public function textoNumeros(int $storeId): string
    {
        $svc = new AnalyzingBIService();
        $b = $svc->buildPayload($storeId);
        $ind = $svc->buildIndicators($storeId);
        $brl = static fn ($v): string => 'R$ ' . number_format((float) $v, 2, ',', '.');
        // Como na tela: margem, markup e participação com 1 casa (58,8%);
        // taxas de juros com até 2 (1,99%, 2,61%).
        $pct = static fn ($v): string => $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';
        $taxa = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%';

        $l = ['NÚMEROS DO ANALYZING BI DESTA LOJA (consultados agora; os mesmos da tela)'];
        $l[] = 'Visão geral: vendido desde o início ' . $brl($b['valor_total']) . '; no mês ' . $brl($b['valor_mensal'])
            . '; lucro estimado total ' . $brl($b['lucro_estimado']) . '; lucro do mês ' . $brl($b['lucro_mensal'])
            . ' (margem ' . $pct($b['margem_mes']) . ', markup ' . $pct($b['markup_mes']) . '); pedidos pagos ' . (int) $b['quantidade_pedidos']
            . '; ticket médio ' . $brl($b['ticket_medio']) . '; meta do mês '
            . ($b['meta_mes']['meta'] ? $brl($b['meta_mes']['meta']) . ', atingido ' . $pct($b['meta_mes']['percentual']) : 'não definida') . '.';

        $lucros = array_slice($b['lucro_produtos'], 0, 5);
        if ($lucros !== []) {
            $l[] = 'Lucro por produto no mês (maiores): ' . implode('; ', array_map(static fn (array $p): string => $p['nome'] . ' ' . $brl($p['lucro_total'])
                . ' (' . rtrim(rtrim(number_format((float) $p['quantidade_vendida'], 2, ',', ''), '0'), ',') . ' un.)', $lucros)) . '.';
        }
        $mais = (array) $b['produto_mais_vendido'];
        $menos = (array) $b['produto_menos_vendido'];
        if (!empty($mais['nome'])) {
            $l[] = 'Mais vendido no mês: ' . $mais['nome'] . ' (' . (float) $mais['quantidade_vendida'] . ' un.). Menos vendido: '
                . ($menos['nome'] ?? '—') . ' (' . (float) ($menos['quantidade_vendida'] ?? 0) . ' un.).';
        }
        $l[] = 'Produtos parados: ' . (int) $b['produtos_parados_total']
            . ($b['produtos_parados'] !== [] ? ' — ' . implode('; ', array_map(static fn (array $p): string => $p['nome'] . ' (' . $brl($p['capital_parado']) . ' parado)', array_slice($b['produtos_parados'], 0, 5))) : '') . '.';
        $l[] = 'Estoque crítico: ' . ($b['estoque_critico'] === [] ? 'nenhum' : implode('; ', array_map(static function (array $p): string {
            $vars = $p['variacoes_esgotadas'] ?? [];
            // variacoes_esgotadas já vem como texto ("Branco · P").
            return $p['nome'] . ' (estoque ' . (float) $p['estoque_atual'] . ', mínimo ' . (float) $p['estoque_minimo'] . ($vars ? ', esgotado: ' . implode(', ', $vars) : '') . ')';
        }, array_slice($b['estoque_critico'], 0, 5)))) . '.';

        $px = $b['pix_parcelamento'];
        $l[] = 'PIX × parcelamento no mês: desconto dado no PIX ' . $brl($px['desconto_pix']) . ' em ' . (int) $px['pedidos_pix'] . ' pedido(s); juros recebidos no cartão '
            . $brl($px['juros_parcelamento']) . ' em ' . (int) $px['pedidos_parcelados'] . ' pedido(s) parcelado(s); desconto de ' . $taxa($px['pix_pct'])
            . ' no PIX equivale a uma taxa de ' . $taxa($px['taxa_equivalente_pix']) . ' ao mês, em comparação com vender em até ' . (int) $px['parcelas_sem_juros'] . 'x sem juros. '
            . 'Acima de ' . (int) $px['parcelas_sem_juros'] . 'x, o parcelamento da loja cobra juros de ' . $taxa($px['taxa_parcelamento']) . ' ao mês. '
            . ($px['taxa_equivalente_pix'] !== null && (float) $px['taxa_equivalente_pix'] > (float) $px['taxa_parcelamento']
                ? 'Leitura da tela: a taxa equivalente do PIX é maior que os juros do parcelamento, então o desconto no PIX sai mais caro para a loja do que parcelar com juros.'
                : 'Leitura da tela: a taxa equivalente do PIX não passa dos juros do parcelamento, então o desconto no PIX não sai mais caro que parcelar com juros.');

        $cap = $ind['capital'];
        $l[] = 'Capital em estoque: ' . $brl($cap['total']) . ' em ' . (int) $cap['unidades'] . ' unidades; parado há 30 dias ou mais ' . $brl($cap['parado'])
            . '; produtos com estoque e sem custo: ' . (int) $cap['sem_custo'] . '.';

        $abc = $ind['abc'];
        $l[] = 'Curva ABC (12 meses): ' . implode('; ', array_map(static fn (string $c, array $r): string => 'classe ' . $c . ' ' . $pct($r['percentual']) . ' da receita em ' . (int) $r['produtos'] . ' produto(s)', array_keys($abc['resumo']), $abc['resumo']))
            . ($abc['itens'] !== [] ? '. Produtos: ' . implode('; ', array_map(static fn (array $i): string => $i['nome'] . ' (' . $i['classe'] . ', ' . $brl($i['receita']) . ', margem ' . $pct($i['margem']) . ', markup ' . $pct($i['markup']) . ')', array_slice($abc['itens'], 0, 8))) : '') . '.';

        $estoque = $ind['estoque'];
        usort($estoque, static fn (array $a, array $c): int => ($a['repor_em_dias'] ?? PHP_INT_MAX) <=> ($c['repor_em_dias'] ?? PHP_INT_MAX));
        $l[] = 'Giro e cobertura (repor primeiro): ' . implode('; ', array_map(static function (array $i) use ($brl): string {
            $cob = $i['cobertura_dias'] === null ? 'sem venda em 90 dias' : 'dura mais ' . $i['cobertura_dias'] . ' dias';
            $est = ($i['cobertura_dias'] !== null && $i['dias_historico'] < 90) ? ' (estimativa: ' . ($i['dias_historico'] < 7 ? 'menos de 7' : $i['dias_historico']) . ' dias de histórico)' : '';
            return $i['nome'] . ': estoque ' . $i['estoque'] . ', ' . $brl($i['capital']) . ' em custo, giro ' . ($i['giro'] === null ? '—' : str_replace('.', ',', (string) $i['giro']) . '×') . ', ' . $cob . $est
                . ($i['repor_em'] ? ', repor até ' . date('d/m/Y', strtotime($i['repor_em'])) : '');
        }, array_slice($estoque, 0, 6))) . '.';

        $cup = $ind['cupons'];
        $l[] = 'Cupons (90 dias): ' . ($cup['cupons'] === [] ? 'nenhum pedido com cupom' : implode('; ', array_map(static fn (array $c): string => $c['codigo'] . ': ' . $c['pedidos'] . ' pedido(s), desconto ' . $brl($c['desconto']) . ', receita ' . $brl($c['receita']) . ', lucro ' . $brl($c['lucro']) . ', margem ' . $pct($c['margem']), array_slice($cup['cupons'], 0, 5)))) . '.';

        $l[] = 'Simulador "Vale a pena comprar este estoque?": não tem número fixo. VPL, TIR e payback dependem do produto, da quantidade, da TMA e das despesas que a pessoa escolhe na tela; para um valor exato, ela deve olhar o resultado lá.';
        if ($b['ideias_investimento'] !== []) {
            $l[] = 'Ideias de investimento mostradas: ' . implode(' | ', array_slice($b['ideias_investimento'], 0, 6));
        }

        return implode("\n", $l);
    }
}
