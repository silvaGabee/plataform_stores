<?php
/**
 * Popula o Analyzing BI de uma loja com histórico de vendas de demonstração.
 *
 *   php backend/tools/seed_bi_demo.php --loja=teste [--meses=6] [--semente=7]
 *   php backend/tools/seed_bi_demo.php --loja=teste --remover
 *
 * Gera pedidos pagos (e alguns cancelados/pendentes) com os produtos que a
 * loja JÁ tem, contando uma história que exercita cada parte do BI:
 *
 *   - faturamento crescendo ~12% ao mês, com mais vendas no fim de semana;
 *   - um produto em alta (o mais caro da loja) e um em queda nos dois últimos
 *     meses (aparece em "produtos parados");
 *   - reajuste de custo do fornecedor três meses atrás — as vendas anteriores
 *     guardam o custo antigo (custo congelado no item);
 *   - PDV e online, dinheiro/cartão/PIX, descontos no balcão.
 *
 * O estoque NÃO é alterado: é histórico para análise, não movimentação.
 *
 * Tudo o que é gerado pertence a clientes de demonstração
 * (demo-bi-N@exemplo.invalid); --remover apaga esses pedidos e esses clientes,
 * e mais nada. Rodar de novo sem --remover substitui a demonstração anterior.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Database\Database;

$opts = getopt('', ['loja:', 'meses::', 'semente::', 'remover']);
$slug = (string) ($opts['loja'] ?? '');
if ($slug === '') {
    fwrite(STDERR, "Informe a loja: --loja=slug\n");
    exit(1);
}
$meses = max(2, min(24, (int) ($opts['meses'] ?? 6)));
mt_srand((int) ($opts['semente'] ?? 7));

$pdo = Database::getConnection();
$store = $pdo->prepare('SELECT id, name FROM stores WHERE slug = ?');
$store->execute([$slug]);
$store = $store->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    fwrite(STDERR, "Loja '{$slug}' não encontrada.\n");
    exit(1);
}
$storeId = (int) $store['id'];
const EMAIL_DEMO = 'demo-bi-%d@exemplo.invalid';

/** Remove a demonstração anterior desta loja. Devolve quantos pedidos saíram. */
$remover = static function () use ($pdo, $storeId): int {
    $ids = $pdo->query("SELECT id FROM users WHERE email LIKE 'demo-bi-%@exemplo.invalid'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        return 0;
    }
    $in = implode(',', array_map('intval', $ids));
    // order_items e payments saem em cascata (FK ON DELETE CASCADE).
    $n = $pdo->exec("DELETE FROM orders WHERE store_id = {$storeId} AND customer_id IN ({$in})");
    // Clientes só são apagados se não tiverem pedido em outra loja.
    $pdo->exec("DELETE FROM users WHERE id IN ({$in}) AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = users.id)");

    return (int) $n;
};

if (isset($opts['remover'])) {
    $n = Database::transaction($remover);
    echo "Demonstração removida da loja '{$store['name']}': {$n} pedido(s).\n";
    exit(0);
}

// ------------------------------------------------------------------ insumos
$produtos = $pdo->prepare('SELECT id, name, cost_price, sale_price FROM products WHERE store_id = ? ORDER BY sale_price DESC');
$produtos->execute([$storeId]);
$produtos = $produtos->fetchAll(PDO::FETCH_ASSOC);
if (count($produtos) < 2) {
    fwrite(STDERR, "A loja precisa de ao menos 2 produtos cadastrados.\n");
    exit(1);
}
$variacoes = [];
$stmt = $pdo->prepare("SELECT product_id, variant_value FROM product_variants WHERE variant_type = 'combinacao' AND product_id = ?");
foreach ($produtos as $p) {
    $stmt->execute([$p['id']]);
    $variacoes[(int) $p['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$equipe = $pdo->prepare("SELECT user_id, role FROM store_members WHERE store_id = ? AND role IN ('gerente', 'funcionario')");
$equipe->execute([$storeId]);
$equipe = $equipe->fetchAll(PDO::FETCH_ASSOC);
$funcionarios = array_values(array_filter($equipe, static fn ($m) => $m['role'] === 'funcionario')) ?: $equipe;

// Papéis na história: o mais caro cresce, o último da lista (mais barato que
// ainda não seja o único) entra em queda nos dois meses finais.
$emAlta = (int) $produtos[0]['id'];
$emQueda = (int) $produtos[count($produtos) - 1]['id'];
$pesoBase = [];
foreach ($produtos as $i => $p) {
    // Produtos baratos vendem mais unidades.
    $pesoBase[(int) $p['id']] = 10 + 6 * $i;
}

$tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
$agora = new DateTimeImmutable('now', $tz);
$inicio = $agora->modify('first day of this month')->modify('-' . ($meses - 1) . ' months')->setTime(0, 0, 0);
$reajuste = $agora->modify('first day of this month')->modify('-3 months'); // custo sobe 8% a partir daqui

$sorteio = static function (array $pesos) {
    $total = array_sum($pesos);
    $r = mt_rand(1, (int) $total);
    foreach ($pesos as $chave => $peso) {
        $r -= $peso;
        if ($r <= 0) {
            return $chave;
        }
    }

    return array_key_last($pesos);
};

// ------------------------------------------------------------------ geração
$resultado = Database::transaction(function () use (
    $pdo, $storeId, $remover, $produtos, $variacoes, $funcionarios, $emAlta, $emQueda, $pesoBase,
    $agora, $inicio, $reajuste, $sorteio
): array {
    $removidos = $remover();

    // Clientes de demonstração.
    $clientes = [];
    $insUser = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $nomes = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabi', 'Heitor', 'Isa', 'João', 'Karen', 'Lucas',
        'Marina', 'Nicolas', 'Olívia', 'Pedro', 'Rafa', 'Sofia', 'Tiago', 'Vitória'];
    foreach ($nomes as $i => $nome) {
        $email = sprintf(EMAIL_DEMO, $i + 1);
        $existe = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $existe->execute([$email]);
        $id = $existe->fetchColumn();
        if (!$id) {
            $insUser->execute([$nome . ' (demo)', $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
            $id = $pdo->lastInsertId();
        }
        $clientes[] = ['id' => (int) $id, 'nome' => $nome];
    }

    $insOrder = $pdo->prepare(
        'INSERT INTO orders (store_id, customer_id, created_by, order_type, delivery_type, delivery_stage, status, total, discount, pdv_customer_name, created_at, access_token)
         VALUES (?, ?, ?, ?, \'retirada\', \'entregue\', ?, ?, ?, ?, ?, ?)'
    );
    $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variant_key, quantity, price, cost_price) VALUES (?, ?, ?, ?, ?, ?)');
    $insPay = $pdo->prepare('INSERT INTO payments (order_id, store_id, method, status, amount, created_at) VALUES (?, ?, ?, ?, ?, ?)');

    $stats = ['pedidos' => 0, 'pagos' => 0, 'faturamento' => 0.0];
    $totalMeses = (int) $inicio->diff($agora)->format('%m') + 12 * (int) $inicio->diff($agora)->format('%y');
    $dia = $inicio;
    while ($dia <= $agora) {
        $mesIdx = ((int) $dia->format('Y') - (int) $inicio->format('Y')) * 12 + (int) $dia->format('n') - (int) $inicio->format('n');
        $ultimosDois = $mesIdx >= $totalMeses - 1;

        // Volume: 3 pedidos/dia no início, +12% ao mês; sábado forte, segunda fraca.
        $dow = (int) $dia->format('N');
        $fatorDia = [1 => 0.7, 2 => 0.9, 3 => 1.0, 4 => 1.0, 5 => 1.25, 6 => 1.5, 7 => 0.8][$dow];
        $media = 3 * (1.12 ** $mesIdx) * $fatorDia;
        $n = max(0, (int) round($media + (mt_rand(-100, 100) / 100) * sqrt($media)));
        if ($dia->format('Y-m-d') === $agora->format('Y-m-d')) {
            // Hoje: só até a hora atual.
            $n = (int) round($n * ((int) $agora->format('G') / 24));
        }

        for ($k = 0; $k < $n; $k++) {
            $hora = mt_rand(9, 20);
            $quando = $dia->setTime($hora, mt_rand(0, 59), mt_rand(0, 59));
            if ($quando > $agora) {
                continue;
            }
            $pesos = $pesoBase;
            $pesos[$emAlta] = (int) round($pesoBase[$emAlta] * (1 + 0.35 * $mesIdx));
            if ($ultimosDois) {
                $pesos[$emQueda] = max(1, (int) round($pesoBase[$emQueda] * 0.08));
            }
            $custoFator = $quando < $reajuste ? 0.92 : 1.0;

            $pdv = mt_rand(1, 100) <= 55;
            $itens = [];
            $nItens = mt_rand(1, 100) <= 70 ? 1 : mt_rand(2, 3);
            for ($j = 0; $j < $nItens; $j++) {
                $pid = (int) $sorteio($pesos);
                if (isset($itens[$pid])) {
                    continue;
                }
                $prod = null;
                foreach ($produtos as $p) {
                    if ((int) $p['id'] === $pid) {
                        $prod = $p;
                    }
                }
                $vars = $variacoes[$pid];
                $itens[$pid] = [
                    'qtd' => mt_rand(1, 100) <= 80 ? 1 : 2,
                    'preco' => (float) $prod['sale_price'],
                    'custo' => round((float) $prod['cost_price'] * $custoFator, 2),
                    'variacao' => $vars ? $vars[array_rand($vars)] : null,
                ];
            }
            $subtotal = 0.0;
            foreach ($itens as $it) {
                $subtotal += $it['qtd'] * $it['preco'];
            }
            $desconto = ($pdv && mt_rand(1, 100) <= 15) ? round($subtotal * mt_rand(5, 10) / 100, 2) : 0.0;
            $total = round($subtotal - $desconto, 2);

            $r = mt_rand(1, 100);
            $status = $r <= 92 ? 'pago' : ($r <= 97 ? 'cancelado' : 'pendente');
            if ($pdv && $status === 'pendente') {
                $status = 'pago'; // venda de balcão não fica pendente
            }
            $metodo = $pdv
                ? $sorteio(['dinheiro' => 30, 'cartao' => 45, 'pix' => 25])
                : $sorteio(['pix' => 60, 'cartao' => 40]);
            $cliente = $clientes[array_rand($clientes)];
            $operador = $pdv ? (int) $funcionarios[array_rand($funcionarios)]['user_id'] : null;

            $insOrder->execute([
                $storeId, $cliente['id'], $operador, $pdv ? 'pdv' : 'online', $status, $total, $desconto,
                $pdv && mt_rand(0, 1) ? $cliente['nome'] : null,
                $quando->format('Y-m-d H:i:s'), bin2hex(random_bytes(16)),
            ]);
            $orderId = (int) $pdo->lastInsertId();
            foreach ($itens as $pid => $it) {
                $insItem->execute([$orderId, $pid, $it['variacao'], $it['qtd'], $it['preco'], $it['custo']]);
            }
            $insPay->execute([
                $orderId, $storeId, $metodo,
                $status === 'pago' ? 'confirmado' : ($status === 'cancelado' ? 'cancelado' : 'pendente'),
                $total, $quando->format('Y-m-d H:i:s'),
            ]);

            $stats['pedidos']++;
            if ($status === 'pago') {
                $stats['pagos']++;
                $stats['faturamento'] += $total;
            }
        }
        $dia = $dia->modify('+1 day');
    }
    $stats['removidos'] = $removidos;

    return $stats;
});

printf(
    "Loja '%s': %d pedidos gerados (%d pagos, R$ %s) de %s até hoje.%s\n",
    $store['name'],
    $resultado['pedidos'],
    $resultado['pagos'],
    number_format($resultado['faturamento'], 2, ',', '.'),
    $inicio->format('d/m/Y'),
    $resultado['removidos'] ? " (substituiu {$resultado['removidos']} pedidos da demonstração anterior)" : ''
);
echo "Para remover: php backend/tools/seed_bi_demo.php --loja={$slug} --remover\n";
