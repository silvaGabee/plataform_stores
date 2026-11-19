<?php

namespace App\Services;

use App\Database\Database;
use PDO;

/**
 * Consultas que o assistente de IA pode usar sobre a loja.
 *
 * A IA não escreve SQL: um modelo que monta consulta pode ler outra loja ou
 * apagar dados. Aqui ficam consultas prontas, só de leitura, e TODAS recebem
 * o id da loja de quem está logado — o modelo não escolhe a loja. O servidor
 * decide quais rodar pela pergunta (AiAssistantService) e manda o resultado.
 *
 * Estoque segue a mesma regra da tela de Estoque (panel-stock.js): cada cor e
 * tamanho é olhado separado; zerado é "esgotado"; "no mínimo" só existe para
 * produto com estoque mínimo cadastrado. Antes a IA via só o total do produto,
 * e um tênis com três numerações zeradas aparecia como "em dia".
 */
final class AiStoreData
{
    private const MAX_LINHAS_ESTOQUE = 40;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Linhas de estoque em alerta, por variação.
     *
     * @return array{esgotados: list<string>, baixos: list<string>, produtos_com_alerta: int, produtos: int, sem_minimo: int}
     */
    public function estoqueEmAlerta(int $storeId): array
    {
        $st = $this->pdo->prepare(
            "SELECT p.id, p.name, p.stock_quantity, p.min_stock, v.variant_type, v.variant_value, v.stock_quantity AS vq
             FROM products p
             LEFT JOIN product_variants v ON v.product_id = p.id AND v.variant_type <> '_meta'
             WHERE p.store_id = ?
             ORDER BY p.name ASC, p.id ASC, v.sort_order ASC, v.id ASC"
        );
        $st->execute([$storeId]);

        $produtos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['id'];
            if (!isset($produtos[$id])) {
                $produtos[$id] = ['nome' => (string) $r['name'], 'qtd' => (int) $r['stock_quantity'], 'min' => (int) $r['min_stock'], 'vars' => []];
            }
            if ($r['variant_type'] !== null) {
                $produtos[$id]['vars'][] = [self::rotuloVariacao((string) $r['variant_type'], (string) $r['variant_value']), (int) $r['vq']];
            }
        }

        $esgotados = [];
        $baixos = [];
        $comAlerta = 0;
        $semMinimo = 0;
        foreach ($produtos as $p) {
            $min = $p['min'];
            if ($min <= 0) {
                $semMinimo++;
            }
            $linhas = $p['vars'] !== [] ? $p['vars'] : [[null, $p['qtd']]];
            $alerta = false;
            foreach ($linhas as [$rotulo, $qtd]) {
                $nome = $p['nome'] . ($rotulo !== null ? ' — ' . $rotulo : '');
                if ($qtd <= 0) {
                    $esgotados[] = $nome . ': 0 un.';
                    $alerta = true;
                } elseif ($min > 0 && $qtd <= $min) {
                    $baixos[] = $nome . ': ' . $qtd . ' un. (mínimo ' . $min . ')';
                    $alerta = true;
                }
            }
            if ($alerta) {
                $comAlerta++;
            }
        }

        return [
            'esgotados' => $esgotados,
            'baixos' => $baixos,
            'produtos_com_alerta' => $comAlerta,
            'produtos' => count($produtos),
            'sem_minimo' => $semMinimo,
        ];
    }

    /** "combinacao" "Vermelho|40" → "Vermelho · 40"; simples "tamanho" "M" → "M". */
    private static function rotuloVariacao(string $tipo, string $valor): string
    {
        if ($tipo === 'combinacao' && strpos($valor, '|') !== false) {
            [$cor, $tam] = explode('|', $valor, 2);

            return trim($cor) . ' · ' . trim($tam);
        }

        return $valor;
    }

    /** Bloco de texto do estoque para a IA. */
    public function textoEstoque(int $storeId): string
    {
        $e = $this->estoqueEmAlerta($storeId);
        $l = ['ESTOQUE (consultado agora, cada cor e tamanho separado; mesma regra da tela Estoque)'];
        $l[] = 'Produtos cadastrados: ' . $e['produtos'] . '. Produtos com algum alerta: ' . $e['produtos_com_alerta']
            . '. Variações esgotadas (0 un.): ' . count($e['esgotados']) . '. Variações no mínimo ou abaixo: ' . count($e['baixos']) . '.';
        if ($e['sem_minimo'] > 0) {
            $l[] = 'Atenção: ' . $e['sem_minimo'] . ' produto(s) não têm estoque mínimo cadastrado; neles só dá para saber quando zeram, não quando estão baixos.';
        }
        $resto = self::MAX_LINHAS_ESTOQUE;
        foreach (['Esgotados' => $e['esgotados'], 'No mínimo ou abaixo' => $e['baixos']] as $titulo => $itens) {
            if ($itens === []) {
                $l[] = $titulo . ': nenhum.';
                continue;
            }
            $mostrar = array_slice($itens, 0, max(0, $resto));
            $resto -= count($mostrar);
            $l[] = $titulo . ':';
            foreach ($mostrar as $i) {
                $l[] = '- ' . $i;
            }
            if (count($itens) > count($mostrar)) {
                $l[] = '- … e mais ' . (count($itens) - count($mostrar)) . ' (lista completa na tela Estoque).';
            }
        }

        return implode("\n", $l);
    }

    /**
     * Produtos citados pelo nome na pergunta (no máximo 3), com preço e
     * estoque por variação. Custo só para quem vê o financeiro.
     */
    public function textoProdutosCitados(int $storeId, string $pergunta, bool $incluirFinanceiro): string
    {
        $st = $this->pdo->prepare('SELECT id, name FROM products WHERE store_id = ?');
        $st->execute([$storeId]);
        $ids = self::produtosNaPergunta($pergunta, $st->fetchAll(PDO::FETCH_KEY_PAIR));
        if ($ids === []) {
            return '';
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT p.id, p.name, p.sale_price, p.promo_price, p.cost_price, p.stock_quantity, p.min_stock,
                    v.variant_type, v.variant_value, v.stock_quantity AS vq
             FROM products p
             LEFT JOIN product_variants v ON v.product_id = p.id AND v.variant_type <> '_meta'
             WHERE p.store_id = ? AND p.id IN ($in)
             ORDER BY p.name, v.sort_order, v.id"
        );
        $st->execute(array_merge([$storeId], $ids));
        $prod = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['id'];
            $prod[$id] = $prod[$id] ?? ['r' => $r, 'vars' => []];
            if ($r['variant_type'] !== null) {
                $prod[$id]['vars'][] = self::rotuloVariacao((string) $r['variant_type'], (string) $r['variant_value']) . ': ' . (int) $r['vq'];
            }
        }
        $l = ['PRODUTOS CITADOS NA PERGUNTA (consultados agora)'];
        foreach ($prod as $p) {
            $r = $p['r'];
            $linha = '- ' . $r['name'] . ': preço de venda ' . self::brl((float) $r['sale_price']);
            if ($r['promo_price'] !== null && (float) $r['promo_price'] > 0) {
                $linha .= ', preço promocional ' . self::brl((float) $r['promo_price']);
            }
            if ($incluirFinanceiro && $r['cost_price'] !== null) {
                $linha .= ', custo ' . self::brl((float) $r['cost_price']);
            }
            $linha .= '; estoque total ' . (int) $r['stock_quantity'] . ' un.; mínimo ' . ((int) $r['min_stock'] > 0 ? (int) $r['min_stock'] : 'não cadastrado');
            if ($p['vars'] !== []) {
                $linha .= '; por variação (un.): ' . implode(', ', $p['vars']);
            }
            $l[] = $linha . '.';
        }

        return implode("\n", $l);
    }

    /**
     * Quais produtos a pergunta cita: o nome inteiro, ou ao menos duas palavras
     * marcantes dele (ex.: "tênis de corrida" acha "Tênis de Corrida Masculino").
     *
     * @param array<int|string, string> $nomes id => nome
     * @return list<int>
     */
    public static function produtosNaPergunta(string $pergunta, array $nomes): array
    {
        $q = ' ' . preg_replace('/[^\p{L}\p{N}]+/u', ' ', AiGuard::normalizar($pergunta)) . ' ';
        $pontos = [];
        foreach ($nomes as $id => $nome) {
            $n = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', AiGuard::normalizar((string) $nome)) ?? '');
            if ($n === '') {
                continue;
            }
            if (strpos($q, ' ' . $n . ' ') !== false) {
                $pontos[(int) $id] = 100;
                continue;
            }
            $palavras = array_filter(explode(' ', $n), static fn (string $w): bool => mb_strlen($w) >= 4);
            $acertos = 0;
            foreach (array_unique($palavras) as $w) {
                if (strpos($q, ' ' . $w . ' ') !== false) {
                    $acertos++;
                }
            }
            if ($acertos >= 2 || ($acertos === 1 && count($palavras) === 1)) {
                $pontos[(int) $id] = $acertos;
            }
        }
        arsort($pontos);

        return array_slice(array_keys($pontos), 0, 3);
    }

    /** Pedidos pendentes: quantos por idade e os 10 mais recentes. */
    public function textoPedidosPendentes(int $storeId): string
    {
        $st = $this->pdo->prepare(
            "SELECT id, order_type, delivery_type, total, created_at FROM orders
             WHERE store_id = ? AND status = 'pendente' ORDER BY created_at DESC"
        );
        $st->execute([$storeId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $hoje = date('Y-m-d');
        $seteDias = date('Y-m-d', strtotime('-6 days'));
        $n = ['hoje' => 0, 'semana' => 0, 'antigos' => 0];
        foreach ($rows as $r) {
            $d = substr((string) $r['created_at'], 0, 10);
            $n[$d === $hoje ? 'hoje' : ($d >= $seteDias ? 'semana' : 'antigos')]++;
        }
        $l = ['PEDIDOS PENDENTES (aguardando pagamento; consultados agora)'];
        $l[] = 'Total: ' . count($rows) . '. Criados hoje: ' . $n['hoje'] . '; nos 6 dias anteriores: ' . $n['semana'] . '; há mais de 7 dias: ' . $n['antigos'] . '.';
        foreach (array_slice($rows, 0, 10) as $r) {
            $l[] = '- Pedido #' . $r['id'] . ' — ' . date('d/m/Y H:i', strtotime((string) $r['created_at']))
                . ' — ' . ($r['order_type'] === 'pdv' ? 'PDV' : 'vitrine')
                . ($r['delivery_type'] ? ' (' . $r['delivery_type'] . ')' : '')
                . ' — ' . self::brl((float) $r['total']);
        }
        if (count($rows) > 10) {
            $l[] = '- … e mais ' . (count($rows) - 10) . ' (lista completa em Entregas).';
        }

        return implode("\n", $l);
    }

    /** Vendas pagas dia a dia nos últimos 7 dias (só para quem vê o financeiro). */
    public function textoVendasPorDia(int $storeId): string
    {
        $inicio = date('Y-m-d', strtotime('-6 days'));
        $st = $this->pdo->prepare(
            // Mesma conta das outras linhas de vendas (ReportService::storeRevenueByType): sem o frete.
            "SELECT DATE(created_at) AS dia, COUNT(*) AS pedidos, SUM(total - shipping_fee) AS total FROM orders
             WHERE store_id = ? AND status = 'pago' AND created_at >= ?
             GROUP BY DATE(created_at)"
        );
        $st->execute([$storeId, $inicio . ' 00:00:00']);
        $porDia = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $porDia[(string) $r['dia']] = $r;
        }
        $l = ['VENDAS PAGAS DIA A DIA, ÚLTIMOS 7 DIAS (consultadas agora; pedidos pagos, sem o frete)'];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $r = $porDia[$d] ?? null;
            $dia = date('d/m', strtotime($d));
            $rotulo = $i === 0 ? 'hoje (' . $dia . ')' : ($i === 1 ? 'ontem (' . $dia . ')' : $dia);
            $l[] = '- ' . $rotulo . ': ' . ($r ? (int) $r['pedidos'] . ' pedido(s), ' . self::brl((float) $r['total']) : '0 pedidos, R$ 0,00');
        }

        return implode("\n", $l);
    }

    private static function brl(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }
}
