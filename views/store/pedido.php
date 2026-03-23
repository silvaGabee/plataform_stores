<?php
$isPaid = (isset($order['status']) && strtolower($order['status']) === 'pago');
$content = ob_start();
?>
<div class="container order-page">
    <div class="order-confirm-wrap">
        <?php if ($isPaid): ?>
        <div class="order-success-badge">
            <span class="order-success-icon" aria-hidden="true">✓</span>
            <span>Pagamento confirmado!</span>
        </div>
        <?php endif; ?>
        <h1 class="order-title">Pedido #<?= $order['id'] ?></h1>
        <div class="order-summary-card">
            <div class="order-summary-row">
                <span class="order-summary-label">Status</span>
                <span class="order-summary-value order-status-<?= htmlspecialchars(strtolower($order['status'] ?? '')) ?>"><?= htmlspecialchars($order['status']) ?></span>
            </div>
            <div class="order-summary-row order-summary-total">
                <span class="order-summary-label">Total</span>
                <span class="order-summary-value">R$ <?= number_format($order['total'], 2, ',', '.') ?></span>
            </div>
            <?php
            $deliveryType = isset($order['delivery_type']) ? strtolower($order['delivery_type']) : 'retirada';
            if ($deliveryType === 'entrega' && !empty($order_address)): ?>
            <div class="order-summary-row">
                <span class="order-summary-label">Entrega</span>
                <span class="order-summary-value"><?= htmlspecialchars($order_address['street'] ?? '') ?>, <?= htmlspecialchars($order_address['number'] ?? '') ?><?= !empty($order_address['complement']) ? ' – ' . htmlspecialchars($order_address['complement']) : '' ?> — <?= htmlspecialchars($order_address['neighborhood'] ?? '') ?>, <?= htmlspecialchars($order_address['city'] ?? '') ?>/<?= htmlspecialchars($order_address['state'] ?? '') ?> — CEP <?= htmlspecialchars($order_address['zipcode'] ?? '') ?></span>
            </div>
            <?php elseif ($deliveryType === 'retirada'): ?>
            <div class="order-summary-row">
                <span class="order-summary-label">Recebimento</span>
                <span class="order-summary-value">Retirada na loja</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="order-card">
            <h2 class="order-card-title">Produtos</h2>
            <div class="order-items-wrap">
                <table class="order-items">
                    <thead>
                        <tr><th>Produto</th><th>Qtd</th><th>Preço</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>R$ <?= number_format($item['price'], 2, ',', '.') ?></td>
                                <td class="order-item-subtotal">R$ <?= number_format($item['quantity'] * $item['price'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($order['payments'])): ?>
        <div class="order-card order-payments-card">
            <h2 class="order-card-title">Pagamentos</h2>
            <ul class="order-payments-list">
                <?php foreach ($order['payments'] as $pay): 
                    $payConfirmed = isset($pay['status']) && strtolower($pay['status']) === 'confirmado';
                ?>
                    <li class="order-payment-item <?= $payConfirmed ? 'order-payment-confirmed' : '' ?>">
                        <span class="order-payment-method"><?= htmlspecialchars($pay['method']) ?></span>
                        <span class="order-payment-amount">R$ <?= number_format($pay['amount'], 2, ',', '.') ?></span>
                        <span class="order-payment-status"><?= htmlspecialchars($pay['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="order-actions">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-primary order-btn-back">Voltar à loja</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
