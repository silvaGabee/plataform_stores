<?php
$content = ob_start();
$deliveryLabels = [
    'solicitado' => 'Solicitado',
    'empacotando' => 'Empacotando',
    'entregue_transportadora' => 'Entregue à transportadora',
    'em_rota' => 'Em rota',
    'entregue' => 'Entregue',
];
?>
<div class="container">
    <h1>Meus pedidos</h1>
    <?php if (!empty($logged_in_used)): ?>
        <p class="text-muted">Pedidos em andamento da sua conta (<?= htmlspecialchars($email ?? '') ?>). Entregues não aparecem aqui.</p>
    <?php else: ?>
        <p class="text-muted">Informe o e-mail usado nas compras para ver seus pedidos em andamento. Ou <a href="<?= base_url() ?>">faça login</a> para ver pela sua conta.</p>
        <form method="get" action="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="card" style="max-width: 400px; margin-bottom: 1.5rem;">
            <label for="meus-pedidos-email">E-mail</label>
            <input type="email" id="meus-pedidos-email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="seu@email.com" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($email_searched ?? false): ?>
        <?php if (empty($orders)): ?>
            <p class="text-muted">Nenhum pedido em andamento para este e-mail.</p>
        <?php else: ?>
            <ul class="meus-pedidos-list">
                <?php foreach ($orders as $o): 
                    $stage = $o['delivery_stage'] ?? 'solicitado';
                    $stageLabel = $deliveryLabels[$stage] ?? $stage;
                    $isRetirada = isset($o['delivery_type']) && strtolower($o['delivery_type']) === 'retirada';
                ?>
                    <li class="meus-pedidos-item">
                        <a href="<?= base_url("loja/{$store['slug']}/pedido/{$o['id']}") ?>">
                            <strong>Pedido #<?= $o['id'] ?></strong> — R$ <?= number_format($o['total'], 2, ',', '.') ?>
                            <span class="meus-pedidos-badge"><?= $isRetirada ? 'Retirada' : 'Entrega' ?> · <?= htmlspecialchars($stageLabel) ?></span>
                            <?php if (!empty($o['tracking_code'])): ?>
                                <span class="meus-pedidos-tracking">Código: <?= htmlspecialchars($o['tracking_code']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
    <p style="margin-top: 1.5rem;"><a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-secondary">← Voltar à loja</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
