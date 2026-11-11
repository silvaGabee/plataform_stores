<?php
/**
 * Faixa de vantagens abaixo do banner. Cada item só aparece quando a loja
 * configurou a regra correspondente — nada de promessa que o checkout não cumpre.
 *
 * @var array $store
 */
$__pixPct = \App\Services\OrderPricing::pixPct($store);
$__parcelas = \App\Services\InstallmentPlan::rules($store);
$__freteMin = isset($store['free_shipping_min']) && $store['free_shipping_min'] !== null ? (float) $store['free_shipping_min'] : null;
$__telefone = trim((string) ($store['phone'] ?? ''));
$__beneficios = [];
if ($__pixPct > 0) {
    $__beneficios[] = ['qr', rtrim(rtrim(number_format($__pixPct, 2, ',', ''), '0'), ',') . '% off no PIX', 'Desconto aplicado no pagamento'];
}
if ($__parcelas['free'] > 1) {
    $__beneficios[] = ['card', 'Até ' . $__parcelas['free'] . 'x sem juros', 'No cartão de crédito'];
}
if ($__freteMin !== null && $__freteMin > 0) {
    $__beneficios[] = ['truck', 'Frete grátis', 'Em compras acima de R$ ' . number_format($__freteMin, 2, ',', '.')];
}
if (store_whatsapp_url($store) !== null) {
    $__beneficios[] = ['chat', 'Atendimento direto', 'WhatsApp ' . $__telefone];
}
?>
<?php if ($__beneficios !== []): ?>
    <section class="store-benefits" aria-label="Vantagens da loja">
        <div class="container">
            <ul class="store-benefits-list" style="--benefits: <?= count($__beneficios) ?>">
                <?php foreach ($__beneficios as [$icone, $titulo, $texto]): ?>
                    <li class="store-benefit">
                        <span class="store-benefit-icon" aria-hidden="true"><?= ui_icon($icone, 20) ?></span>
                        <span class="store-benefit-copy">
                            <strong class="store-benefit-title"><?= htmlspecialchars($titulo) ?></strong>
                            <span class="store-benefit-text"><?= htmlspecialchars($texto) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>
