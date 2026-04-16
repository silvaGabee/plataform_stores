<?php
ob_start();
$deliveryLabels = [
    'solicitado' => 'Solicitado',
    'empacotando' => 'Empacotando',
    'entregue_transportadora' => 'Entregue à transportadora',
    'em_rota' => 'Em rota',
    'entregue' => 'Entregue',
];
?>
<div class="store-subpage store-meus-pedidos">
    <div class="store-subpage-inner container">
        <div class="store-subpage-top">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-subpage-back-pill">Voltar à loja</a>
        </div>
        <header class="store-subpage-head">
            <h1 id="store-subpage-title" class="store-subpage-title">Meus pedidos</h1>
            <?php if (!empty($logged_in_used)): ?>
                <p class="store-subpage-lead">Acompanhe pedidos em andamento da conta <strong><?= htmlspecialchars($email ?? '') ?></strong>. Pedidos já entregues não aparecem nesta lista.</p>
            <?php else: ?>
                <p class="store-subpage-lead">Consulte pedidos em andamento pelo e-mail usado na compra. Você também pode <a href="<?= base_url() ?>">entrar na plataforma</a> com sua conta.</p>
            <?php endif; ?>
        </header>

        <div class="store-subpage-body">
            <?php if (empty($logged_in_used)): ?>
                <form method="get" action="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="store-subpage-card store-meus-pedidos-form" aria-labelledby="store-meus-pedidos-form-title">
                    <h2 id="store-meus-pedidos-form-title" class="store-subpage-card-title">Buscar por e-mail</h2>
                    <label for="meus-pedidos-email">E-mail</label>
                    <input type="email" id="meus-pedidos-email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="seu@email.com" required autocomplete="email">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Buscar pedidos</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($email_searched ?? false): ?>
                <?php if (empty($orders)): ?>
                    <div class="store-empty-state store-empty-state--compact" role="status">
                        <div class="store-empty-state-icon" aria-hidden="true">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M3.27 6.96L12 12.01L20.73 6.96" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 22.08V12" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h2 class="store-empty-state-title">Nenhum pedido em andamento</h2>
                        <p class="store-empty-state-text">Não encontramos pedidos em aberto para este e-mail nesta loja. Se acabou de comprar, aguarde alguns instantes ou confira o e-mail informado.</p>
                    </div>
                <?php else: ?>
                    <ul class="meus-pedidos-list">
                        <?php foreach ($orders as $o):
                            $stage = $o['delivery_stage'] ?? 'solicitado';
                            $stageLabel = $deliveryLabels[$stage] ?? $stage;
                            $isRetirada = isset($o['delivery_type']) && strtolower($o['delivery_type']) === 'retirada';
                            ?>
                            <li class="meus-pedidos-item">
                                <a href="<?= base_url("loja/{$store['slug']}/pedido/{$o['id']}") ?>" class="meus-pedidos-card-link">
                                    <div class="meus-pedidos-card-main">
                                        <span class="meus-pedidos-card-id">Pedido #<?= (int) $o['id'] ?></span>
                                        <span class="meus-pedidos-card-price">R$ <?= number_format($o['total'], 2, ',', '.') ?></span>
                                    </div>
                                    <div class="meus-pedidos-card-meta">
                                        <span class="meus-pedidos-pill"><?= $isRetirada ? 'Retirada' : 'Entrega' ?></span>
                                        <span class="meus-pedidos-pill meus-pedidos-pill--stage"><?= htmlspecialchars($stageLabel) ?></span>
                                    </div>
                                    <?php if (!empty($o['tracking_code'])): ?>
                                        <span class="meus-pedidos-tracking">Rastreio: <?= htmlspecialchars($o['tracking_code']) ?></span>
                                    <?php endif; ?>
                                    <span class="meus-pedidos-card-cta">Ver detalhes</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
