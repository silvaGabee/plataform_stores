<?php
ob_start();
$deliveryLabels = [
    'solicitado' => 'Pedido recebido',
    'empacotando' => 'Em separação',
    'entregue_transportadora' => 'Com a transportadora',
    'em_rota' => 'Saiu para entrega',
    'entregue' => 'Entregue',
];
$grupos = ['aguardando' => [], 'andamento' => [], 'concluidos' => []];
foreach ($orders as $o) {
    $st = strtolower((string) ($o['status'] ?? 'pendente'));
    if ($st === 'pendente') {
        $grupos['aguardando'][] = $o;
    } elseif ($st === 'pago' && ($o['delivery_stage'] ?? '') !== 'entregue') {
        $grupos['andamento'][] = $o;
    } else {
        $grupos['concluidos'][] = $o;
    }
}
$titulos = [
    'aguardando' => 'Aguardando pagamento',
    'andamento' => 'Em andamento',
    'concluidos' => 'Concluídos e cancelados',
];
?>
<div class="store-subpage store-meus-pedidos" id="meus-pedidos" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <div class="store-subpage-inner container">
        <div class="store-subpage-top">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-subpage-back-pill"><?= ui_icon('arrow-left', 16) ?> Voltar à loja</a>
        </div>
        <header class="store-subpage-head">
            <h1 class="store-subpage-title">Meus pedidos</h1>
            <p class="store-subpage-lead">Todos os pedidos da conta <strong><?= htmlspecialchars($email ?? '') ?></strong> nesta loja.</p>
        </header>

        <div class="store-subpage-body">
            <?php if (empty($orders)): ?>
                <div class="store-empty-state store-empty-state--compact" role="status">
                    <div class="store-empty-state-icon" aria-hidden="true"><?= ui_icon('package', 40) ?></div>
                    <h2 class="store-empty-state-title">Você ainda não fez pedidos aqui</h2>
                    <p class="store-empty-state-text">Quando comprar, o pedido aparece nesta página com o andamento da entrega.</p>
                    <a class="btn btn-primary" href="<?= base_url("loja/{$store['slug']}") ?>">Ver produtos</a>
                </div>
            <?php else: ?>
                <?php foreach ($grupos as $chave => $lista): if (!$lista) continue; ?>
                    <section class="ui-orders-group" aria-labelledby="mp-<?= $chave ?>">
                        <h2 class="ui-orders-group-title" id="mp-<?= $chave ?>"><?= $titulos[$chave] ?> <span class="ui-count"><?= count($lista) ?></span></h2>
                        <ul class="ui-orders">
                            <?php foreach ($lista as $o):
                                $st = strtolower((string) $o['status']);
                                $url = $o['order_url'] ?? base_url("loja/{$store['slug']}/pedido/{$o['id']}");
                                $isRetirada = strtolower((string) ($o['delivery_type'] ?? '')) === 'retirada';
                                if ($st === 'pendente') {
                                    $chip = ['Aguardando pagamento', 'warn'];
                                } elseif ($st === 'cancelado') {
                                    $chip = [empty($o['cancelled_by']) && !empty($o['expires_at']) ? 'Cancelado: prazo expirou' : 'Cancelado', 'muted'];
                                } else {
                                    $stage = (string) ($o['delivery_stage'] ?? 'solicitado');
                                    $chip = [$isRetirada && $stage === 'entregue' ? 'Retirado' : ($deliveryLabels[$stage] ?? $stage), $stage === 'entregue' ? 'ok' : 'info'];
                                }
                                $data = '';
                                try { $data = (new DateTime($o['created_at']))->format('d/m/Y H:i'); } catch (Exception $e) {}
                                ?>
                                <li class="ui-order" data-id="<?= (int) $o['id'] ?>">
                                    <div class="ui-order-main">
                                        <a class="ui-order-id" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Pedido #<?= (int) $o['id'] ?></a>
                                        <span class="text-muted"><?= htmlspecialchars($data) ?> · <?= $isRetirada ? 'Retirada na loja' : 'Entrega' ?><?= !empty($o['payment_method']) ? ' · ' . ($o['payment_method'] === 'pix' ? 'PIX' : ($o['payment_method'] === 'cartao' ? 'Cartão' : 'Dinheiro')) : '' ?></span>
                                        <?php if (!empty($o['tracking_code']) && $st === 'pago'): ?>
                                            <span class="ui-order-track">Rastreio: <strong><?= htmlspecialchars($o['tracking_code']) ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ui-order-side">
                                        <strong class="ui-order-total">R$ <?= number_format((float) $o['total'], 2, ',', '.') ?></strong>
                                        <span class="ui-badge ui-badge--<?= $chip[1] ?>"><?= htmlspecialchars($chip[0]) ?></span>
                                        <?php if ($st === 'pendente' && !empty($o['expires_at'])): ?>
                                            <span class="ui-order-countdown" data-expires="<?= htmlspecialchars($o['expires_at']) ?>" data-now="<?= date('Y-m-d H:i:s') ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ui-order-actions">
                                        <?php if ($st === 'pendente'): ?>
                                            <button type="button" class="btn btn-ghost btn-sm js-cancel-order">Cancelar</button>
                                            <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>#pagamento"><?= ($o['payment_method'] ?? '') === 'cartao' ? 'Ver pedido' : 'Pagar agora' ?></a>
                                        <?php else: ?>
                                            <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Ver detalhes</a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/store-orders.js') . '"></script>';
require __DIR__ . '/layout_store.php';
