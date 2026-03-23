<?php
$productRepo = new \App\Repositories\ProductRepository();
$cartItems = [];
$total = 0;
foreach ($cart as $productId => $qty) {
    $p = $productRepo->findByIdAndStore($productId, $store['id']);
    if ($p) {
        $cartItems[] = ['product' => $p, 'quantity' => $qty];
        $total += $p['sale_price'] * $qty;
    }
}
$content = ob_start();
?>
<div class="container checkout-page">
    <div class="checkout-wrap">
        <h1 class="checkout-title">Finalizar compra</h1>
        <div class="checkout-total-box">
            <span class="checkout-total-label">Total do pedido</span>
            <span class="checkout-total-value">R$ <?= number_format($total, 2, ',', '.') ?></span>
        </div>
        <div class="checkout-card">
            <form id="checkout-form" class="checkout-form">
                <input type="hidden" name="store_slug" value="<?= htmlspecialchars($store['slug']) ?>">
                <div class="checkout-field">
                    <label for="checkout-name">Seu nome *</label>
                    <input type="text" id="checkout-name" name="customer_name" required placeholder="Nome completo" value="<?= htmlspecialchars($checkout_customer_name ?? '') ?>">
                </div>
                <div class="checkout-field">
                    <label for="checkout-email">E-mail *</label>
                    <input type="email" id="checkout-email" name="customer_email" required placeholder="seu@email.com" value="<?= htmlspecialchars($checkout_customer_email ?? '') ?>">
                </div>
                <div class="checkout-field">
                    <label>Como deseja receber? *</label>
                    <div class="checkout-delivery-options">
                        <label class="checkout-radio"><input type="radio" name="delivery_type" value="retirada" checked> Retirar na loja</label>
                        <label class="checkout-radio"><input type="radio" name="delivery_type" value="entrega"> Entrega</label>
                    </div>
                </div>
                <div id="checkout-address-block" class="checkout-address-block hidden">
                    <p class="checkout-address-intro">Para entrega, use um endereço cadastrado ou cadastre um novo.</p>
                    <div class="checkout-field">
                        <label for="checkout-address-select">Endereço de entrega</label>
                        <select id="checkout-address-select" class="checkout-address-select">
                            <option value="">Carregando...</option>
                        </select>
                        <p id="checkout-address-none" class="checkout-address-none hidden">Nenhum endereço cadastrado para este e-mail. Preencha abaixo para cadastrar.</p>
                    </div>
                    <div id="checkout-address-form" class="checkout-address-form hidden">
                        <div class="checkout-field">
                            <label for="addr-street">Rua *</label>
                            <input type="text" id="addr-street" name="addr_street" placeholder="Rua, avenida">
                        </div>
                        <div class="checkout-address-row">
                            <div class="checkout-field">
                                <label for="addr-number">Número *</label>
                                <input type="text" id="addr-number" name="addr_number" placeholder="Nº">
                            </div>
                            <div class="checkout-field">
                                <label for="addr-complement">Complemento</label>
                                <input type="text" id="addr-complement" name="addr_complement" placeholder="Apto, bloco">
                            </div>
                        </div>
                        <div class="checkout-field">
                            <label for="addr-neighborhood">Bairro</label>
                            <input type="text" id="addr-neighborhood" name="addr_neighborhood" placeholder="Bairro">
                        </div>
                        <div class="checkout-address-row">
                            <div class="checkout-field">
                                <label for="addr-city">Cidade *</label>
                                <input type="text" id="addr-city" name="addr_city" placeholder="Cidade">
                            </div>
                            <div class="checkout-field">
                                <label for="addr-state">UF *</label>
                                <input type="text" id="addr-state" name="addr_state" placeholder="SC" maxlength="2">
                            </div>
                            <div class="checkout-field">
                                <label for="addr-zipcode">CEP *</label>
                                <input type="text" id="addr-zipcode" name="addr_zipcode" placeholder="00000-000">
                            </div>
                        </div>
                        <button type="button" id="checkout-save-address" class="btn btn-secondary btn-sm">Salvar endereço e usar</button>
                    </div>
                </div>
                <div class="checkout-field">
                    <label for="checkout-payment">Forma de pagamento *</label>
                    <select id="checkout-payment" name="payment_method">
                        <option value="pix">PIX</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="cartao">Cartão</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary checkout-submit">Gerar pedido e pagamento</button>
                </div>
            </form>
        </div>
        <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="checkout-back">← Voltar ao carrinho</a>
        <div id="payment-area" class="checkout-payment-area hidden">
            <h2 class="checkout-payment-title">Pagamento PIX</h2>
            <p class="checkout-payment-desc">Escaneie o QR Code com o app do seu banco:</p>
            <div id="pix-qr-container" class="checkout-pix-qr"></div>
            <p id="payment-status" class="checkout-payment-status">Aguardando pagamento...</p>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$baseUrl = rtrim(base_url(), '/');
$extra_js = "
<script>window.BASE_URL = " . json_encode($baseUrl) . "; const storeSlug = " . json_encode($store['slug']) . "; const cartData = " . json_encode(array_map(function ($item) {
    return ['product_id' => $item['product']['id'], 'quantity' => $item['quantity']];
}, $cartItems)) . ";</script>
<script src=\"" . asset('js/checkout.js') . "\"></script>
";
require __DIR__ . '/layout_store.php';
