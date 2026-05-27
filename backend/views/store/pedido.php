<?php
$isPaid = (isset($order['status']) && strtolower($order['status']) === 'pago');
$statusKey = strtolower($order['status'] ?? 'pendente');
$statusLabels = [
    'pendente' => 'Pendente',
    'pago' => 'Pago',
    'cancelado' => 'Cancelado',
    'entregue' => 'Entregue',
];
$statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);
$deliveryType = isset($order['delivery_type']) ? strtolower($order['delivery_type']) : 'retirada';
$paymentMethodLabels = [
    'pix' => 'PIX',
    'cartao' => 'Cartão',
    'dinheiro' => 'Dinheiro',
];
$orderDate = '';
if (!empty($order['created_at'])) {
    try {
        $dt = new DateTime($order['created_at']);
        $orderDate = $dt->format('d/m/Y \à\s H:i');
    } catch (Exception $e) {
        $orderDate = '';
    }
}
$content = ob_start();
?>
<div class="container order-page">
    <div class="order-shell<?= $isPaid ? ' order-shell--paid' : '' ?>">
        <?php if ($isPaid): ?>
        <header class="order-success-hero">
            <div class="order-success-hero-glow" aria-hidden="true"></div>
            <div class="order-success-hero-icon" aria-hidden="true">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <p class="order-success-hero-eyebrow">Tudo certo com seu pedido</p>
            <h1 class="order-success-hero-title">Pagamento confirmado</h1>
            <p class="order-success-hero-meta">
                <span class="order-success-hero-order">Pedido #<?= (int) $order['id'] ?></span>
                <?php if ($orderDate !== ''): ?>
                    <span class="order-success-hero-dot" aria-hidden="true">·</span>
                    <time datetime="<?= htmlspecialchars($order['created_at']) ?>"><?= htmlspecialchars($orderDate) ?></time>
                <?php endif; ?>
            </p>
            <div class="order-success-hero-total">
                <span class="order-success-hero-total-label">Valor pago</span>
                <span class="order-success-hero-total-value">R$ <?= number_format($order['total'], 2, ',', '.') ?></span>
            </div>
        </header>
        <?php else: ?>
        <header class="order-hero">
            <p class="order-hero-eyebrow">Detalhes do pedido</p>
            <h1 class="order-hero-title">Pedido #<?= (int) $order['id'] ?></h1>
            <?php if ($orderDate !== ''): ?>
                <p class="order-hero-date"><time datetime="<?= htmlspecialchars($order['created_at']) ?>"><?= htmlspecialchars($orderDate) ?></time></p>
            <?php endif; ?>
        </header>
        <?php endif; ?>

        <div class="order-body">
            <?php if (!$isPaid): ?>
            <section class="order-panel order-panel--summary" aria-label="Resumo do pedido">
                <div class="order-stat-grid">
                    <div class="order-stat">
                        <span class="order-stat-label">Status</span>
                        <span class="order-stat-badge order-stat-badge--<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    </div>
                    <div class="order-stat order-stat--total">
                        <span class="order-stat-label">Total</span>
                        <span class="order-stat-value order-stat-value--amount">R$ <?= number_format($order['total'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($deliveryType === 'entrega' && !empty($order_address)): ?>
            <section class="order-info-card order-info-card--delivery" aria-label="Entrega">
                <div class="order-info-card-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.75"/></svg>
                </div>
                <div class="order-info-card-content">
                    <span class="order-info-card-label">Entrega no endereço</span>
                    <p class="order-info-card-text">
                        <?= htmlspecialchars($order_address['street'] ?? '') ?>, <?= htmlspecialchars($order_address['number'] ?? '') ?><?= !empty($order_address['complement']) ? ' — ' . htmlspecialchars($order_address['complement']) : '' ?><br>
                        <?= htmlspecialchars($order_address['neighborhood'] ?? '') ?><?= !empty($order_address['neighborhood']) ? ' · ' : '' ?><?= htmlspecialchars($order_address['city'] ?? '') ?>/<?= htmlspecialchars($order_address['state'] ?? '') ?> · CEP <?= htmlspecialchars($order_address['zipcode'] ?? '') ?>
                    </p>
                </div>
            </section>
            <?php elseif ($deliveryType === 'retirada'): ?>
            <section class="order-info-card order-info-card--pickup" aria-label="Retirada">
                <div class="order-info-card-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke="currentColor" stroke-width="1.75"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="1.75"/></svg>
                </div>
                <div class="order-info-card-content">
                    <span class="order-info-card-label">Recebimento</span>
                    <p class="order-info-card-text order-info-card-text--strong">Retirada na loja</p>
                    <p class="order-info-card-hint">Apresente o número do pedido no balcão.</p>
                </div>
            </section>
            <?php endif; ?>

            <section class="order-panel order-panel--products" aria-labelledby="order-products-title">
                <h2 id="order-products-title" class="order-panel-title">Itens do pedido</h2>
                <ul class="order-product-list">
                    <?php foreach ($order['items'] as $item):
                        $lineTotal = $item['quantity'] * $item['price'];
                    ?>
                    <li class="order-product-item">
                        <div class="order-product-qty" aria-hidden="true"><?= (int) $item['quantity'] ?></div>
                        <div class="order-product-body">
                            <div class="order-product-main">
                                <span class="order-product-name"><?= htmlspecialchars($item['product_name']) ?></span>
                                <span class="order-product-line-total">R$ <?= number_format($lineTotal, 2, ',', '.') ?></span>
                            </div>
                            <p class="order-product-meta">R$ <?= number_format($item['price'], 2, ',', '.') ?> cada</p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="order-products-footer">
                    <span>Total dos itens</span>
                    <strong>R$ <?= number_format($order['total'], 2, ',', '.') ?></strong>
                </div>
            </section>

            <?php if (!empty($order['payments'])): ?>
            <section class="order-panel order-panel--payments" aria-labelledby="order-payments-title">
                <h2 id="order-payments-title" class="order-panel-title">Pagamento</h2>
                <ul class="order-payment-list">
                    <?php foreach ($order['payments'] as $pay):
                        $payConfirmed = isset($pay['status']) && strtolower($pay['status']) === 'confirmado';
                        $methodKey = strtolower($pay['method'] ?? '');
                        $methodLabel = $paymentMethodLabels[$methodKey] ?? ucfirst($methodKey);
                        $payStatusKey = strtolower($pay['status'] ?? '');
                        $payStatusLabels = ['pendente' => 'Pendente', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado'];
                        $payStatusLabel = $payStatusLabels[$payStatusKey] ?? ucfirst($payStatusKey);
                    ?>
                    <li class="order-payment-card <?= $payConfirmed ? 'order-payment-card--confirmed' : '' ?>">
                        <div class="order-payment-card-icon order-payment-card-icon--<?= htmlspecialchars($methodKey) ?>" aria-hidden="true">
                            <?php if ($methodKey === 'pix'): ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 7h16v10H4V7z" stroke="currentColor" stroke-width="1.75"/><path d="M7 10.5h4M7 13.5h2.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            <?php else: ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M2 10h20" stroke="currentColor" stroke-width="1.75"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="order-payment-card-body">
                            <div class="order-payment-card-head">
                                <span class="order-payment-card-method"><?= htmlspecialchars($methodLabel) ?></span>
                                <span class="order-payment-card-amount">R$ <?= number_format($pay['amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="order-payment-card-foot">
                                <span class="order-payment-card-status order-payment-card-status--<?= htmlspecialchars($payStatusKey) ?>"><?= htmlspecialchars($payStatusLabel) ?></span>
                                <?php if (!empty($pay['card_last4'])): ?>
                                <span class="order-payment-card-extra">•••• <?= htmlspecialchars($pay['card_last4']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($payConfirmed): ?>
                        <span class="order-payment-card-check" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>

        <footer class="order-footer">
            <p class="order-footer-note"><?= $isPaid ? 'Obrigado pela compra! Guarde este comprovante.' : 'Acompanhe o status do seu pedido nesta página.' ?></p>
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-primary order-footer-btn">Voltar à loja</a>
        </footer>
    </div>
</div>
<?php
$content = ob_get_clean();
$baseUrl = rtrim(base_url(), '/');
$extra_js = '';
if ($isPaid) {
    $extra_js = '<script>(function(){'
        . 'var storeId=' . (int) $store['id'] . ';'
        . 'var slug=' . json_encode($store['slug']) . ';'
        . 'var base=' . json_encode($baseUrl) . ';'
        . 'try{var c=JSON.parse(sessionStorage.getItem("cart")||"{}");'
        . 'delete c[storeId];delete c[String(storeId)];if(slug)delete c[slug];'
        . 'sessionStorage.setItem("cart",JSON.stringify(c));}catch(e){}'
        . 'if(base&&slug){fetch(base+"/api/loja/"+encodeURIComponent(slug)+"/cart/clear",{method:"POST",headers:{"Content-Type":"application/json"}}).catch(function(){});}'
        . '})();</script>';
}
require __DIR__ . '/layout_store.php';
