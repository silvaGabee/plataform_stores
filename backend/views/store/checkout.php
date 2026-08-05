<?php
require __DIR__ . '/_cart_items_build.php';
$total = $cartTotal;
$content = ob_start();
?>
<div class="container checkout-page">
    <div class="checkout-wrap">
        <header class="checkout-header">
            <h1 class="checkout-title">Finalizar compra</h1>
            <p class="checkout-lead">Confira seus dados e escolha como receber e pagar.</p>
        </header>
        <div class="checkout-shell">
            <div class="checkout-summary-bar">
                <div class="checkout-summary-copy">
                    <span class="checkout-summary-eyebrow">Resumo</span>
                    <span class="checkout-summary-label">Total do pedido</span>
                </div>
                <span class="checkout-summary-amount">R$ <?= number_format($total, 2, ',', '.') ?></span>
            </div>
            <form id="checkout-form" class="checkout-form">
                <input type="hidden" name="store_slug" value="<?= htmlspecialchars($store['slug']) ?>">
                <?php
                // Nome e e-mail vêm da conta logada e são só exibidos: o pedido
                // é sempre lançado para quem está na sessão, então editá-los
                // aqui não mudaria nada no servidor. Alterar os dados é na
                // página "Minha conta".
                ?>
                <section class="checkout-form-section">
                    <h2 class="checkout-form-section-title">Seus dados</h2>
                    <div class="checkout-field">
                        <label for="checkout-name">Seu nome</label>
                        <input type="text" id="checkout-name" name="customer_name" readonly value="<?= htmlspecialchars($checkout_customer_name ?? '') ?>">
                    </div>
                    <div class="checkout-field">
                        <label for="checkout-email">E-mail</label>
                        <input type="email" id="checkout-email" name="customer_email" readonly value="<?= htmlspecialchars($checkout_customer_email ?? '') ?>">
                    </div>
                    <p class="checkout-field-hint">Comprando como <strong><?= htmlspecialchars($checkout_customer_email ?? '') ?></strong>. Para alterar, edite em <a href="<?= base_url('minha-conta') ?>">Minha conta</a>.</p>
                </section>
                <section class="checkout-form-section">
                    <h2 class="checkout-form-section-title">Entrega</h2>
                    <div class="checkout-field">
                        <span class="checkout-field-sublabel">Como deseja receber? *</span>
                        <div class="checkout-choice-grid checkout-choice-grid--2">
                            <label class="checkout-choice">
                                <input type="radio" name="delivery_type" value="retirada" checked>
                                <span class="checkout-choice-icon checkout-choice-icon--store" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="1.75"/></svg>
                                </span>
                                <span class="checkout-choice-text">
                                    <span class="checkout-choice-title">Retirar na loja</span>
                                    <span class="checkout-choice-desc">Busque no local</span>
                                </span>
                                <span class="checkout-choice-mark" aria-hidden="true"></span>
                            </label>
                            <label class="checkout-choice">
                                <input type="radio" name="delivery_type" value="entrega">
                                <span class="checkout-choice-icon checkout-choice-icon--delivery" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M1 3h15v11H1V3z" stroke="currentColor" stroke-width="1.75"/><path d="M16 8h4l3 5v5h-7V8z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><circle cx="5.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="1.75"/><circle cx="18.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="1.75"/></svg>
                                </span>
                                <span class="checkout-choice-text">
                                    <span class="checkout-choice-title">Entrega</span>
                                    <span class="checkout-choice-desc">Receba no endereço</span>
                                </span>
                                <span class="checkout-choice-mark" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>
                </section>
                <div id="checkout-address-block" class="checkout-address-block hidden">
                    <h3 class="checkout-address-heading">Endereço de entrega</h3>
                    <p class="checkout-address-intro">Escolha um endereço salvo ou cadastre um novo para receber o pedido.</p>

                    <div id="checkout-address-empty" class="checkout-address-empty hidden">
                        <p class="checkout-address-empty-text">Nenhum endereço cadastrado para este e-mail.</p>
                        <button type="button" class="btn btn-secondary btn-sm checkout-address-add-btn" id="checkout-add-address-empty">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Adicionar novo endereço
                        </button>
                    </div>

                    <div id="checkout-address-picker" class="checkout-address-picker hidden">
                        <div class="checkout-field checkout-field--address-select">
                            <label for="checkout-address-select">Endereço salvo</label>
                            <div class="checkout-address-select-wrap">
                                <span class="checkout-address-select-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.75"/></svg>
                                </span>
                                <select id="checkout-address-select" class="checkout-address-select" aria-label="Endereço de entrega">
                                    <option value="">Carregando...</option>
                                </select>
                                <span class="checkout-address-select-chevron" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm checkout-address-add-btn" id="checkout-add-address">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Adicionar novo endereço
                        </button>
                    </div>

                    <div id="checkout-address-form" class="checkout-address-form-panel hidden">
                        <div class="checkout-address-form-head">
                            <h4 class="checkout-address-form-title">Novo endereço</h4>
                            <button type="button" class="checkout-address-form-cancel" id="checkout-cancel-address">Cancelar</button>
                        </div>
                        <div class="checkout-address-form-body">
                            <div class="checkout-field">
                                <label for="addr-zipcode">CEP *</label>
                                <input type="text" id="addr-zipcode" name="addr_zipcode" placeholder="00000-000" inputmode="numeric" autocomplete="postal-code" maxlength="9">
                                <p id="addr-cep-status" class="cep-lookup-status" role="status" aria-live="polite"></p>
                            </div>
                            <div class="checkout-field">
                                <label for="addr-street">Rua *</label>
                                <input type="text" id="addr-street" name="addr_street" placeholder="Rua, avenida" autocomplete="street-address">
                            </div>
                            <div class="checkout-address-row checkout-address-row--double">
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
                            <div class="checkout-address-row checkout-address-row--double">
                                <div class="checkout-field">
                                    <label for="addr-city">Cidade *</label>
                                    <input type="text" id="addr-city" name="addr_city" placeholder="Cidade" autocomplete="address-level2">
                                </div>
                                <div class="checkout-field">
                                    <label for="addr-state">UF *</label>
                                    <input type="text" id="addr-state" name="addr_state" placeholder="SC" maxlength="2" autocomplete="address-level1" style="text-transform: uppercase">
                                </div>
                            </div>
                        </div>
                        <button type="button" id="checkout-save-address" class="btn btn-primary btn-sm checkout-address-save-btn">Salvar e usar este endereço</button>
                    </div>
                </div>
                <section class="checkout-form-section checkout-form-section--payment">
                    <h2 class="checkout-form-section-title">Pagamento</h2>
                    <div class="checkout-field checkout-field--payment">
                        <span class="checkout-field-sublabel">Escolha como pagar *</span>
                        <div class="checkout-choice-list" role="radiogroup" aria-label="Forma de pagamento">
                            <label class="checkout-choice">
                                <input type="radio" name="payment_method" value="pix" checked>
                                <span class="checkout-choice-icon checkout-choice-icon--pix" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="checkout-choice-text">
                                    <span class="checkout-choice-title">PIX</span>
                                    <span class="checkout-choice-desc">QR Code · confirmação rápida</span>
                                </span>
                                <span class="checkout-choice-mark" aria-hidden="true"></span>
                            </label>
                            <label class="checkout-choice">
                                <input type="radio" name="payment_method" value="cartao">
                                <span class="checkout-choice-icon checkout-choice-icon--card" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M2 10h20" stroke="currentColor" stroke-width="1.75"/></svg>
                                </span>
                                <span class="checkout-choice-text">
                                    <span class="checkout-choice-title">Cartão</span>
                                    <span class="checkout-choice-desc">Débito ou crédito · pagamento seguro</span>
                                </span>
                                <span class="checkout-choice-mark" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>
                    <div id="checkout-card-block" class="checkout-card-block hidden" aria-labelledby="checkout-card-heading">
                        <h3 id="checkout-card-heading" class="checkout-card-heading">Dados do cartão</h3>
                        <p class="checkout-card-intro">Preencha os campos abaixo.</p>

                        <div class="checkout-card-scene" aria-hidden="true">
                            <div class="checkout-card-flipper" id="checkout-card-flipper">
                                <div class="checkout-card-face checkout-card-face--front">
                                    <div class="checkout-card-face-top">
                                        <span class="checkout-card-chip" aria-hidden="true"></span>
                                        <span class="checkout-card-contactless" aria-hidden="true">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8.5 8.5a4 4 0 015 0M6 11a7 7 0 0112 0M4 13.5a10 10 0 0116 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        </span>
                                        <span class="checkout-card-bank"><?= htmlspecialchars(mb_strtoupper(mb_substr($store['name'] ?? 'LOJA', 0, 14))) ?></span>
                                    </div>
                                    <p class="checkout-card-preview-number" id="card-preview-number">•••• •••• •••• ••••</p>
                                    <div class="checkout-card-preview-meta">
                                        <div class="checkout-card-preview-exp-wrap">
                                            <span class="checkout-card-preview-label">Validade</span>
                                            <span class="checkout-card-preview-exp" id="card-preview-expiry">MM/AA</span>
                                        </div>
                                    </div>
                                    <p class="checkout-card-preview-name" id="card-preview-name">SEU NOME</p>
                                </div>
                                <div class="checkout-card-face checkout-card-face--back">
                                    <div class="checkout-card-stripe"></div>
                                    <div class="checkout-card-cvv-panel">
                                        <span class="checkout-card-preview-label">CVV</span>
                                        <span class="checkout-card-preview-cvv" id="card-preview-cvv">•••</span>
                                    </div>
                                    <p class="checkout-card-back-hint">Código de segurança no verso do cartão</p>
                                </div>
                            </div>
                        </div>

                        <div class="checkout-card-form">
                            <div class="checkout-field checkout-field--card-num">
                                <label for="card-number" class="visually-hidden">Número do cartão</label>
                                <input type="text" id="card-number" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="Número do cartão" maxlength="19" aria-label="Número do cartão">
                                <span class="checkout-card-input-icon checkout-card-input-icon--card" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 10h20" stroke="currentColor" stroke-width="1.5"/></svg>
                                </span>
                            </div>
                            <div class="checkout-field">
                                <label for="card-holder" class="visually-hidden">Nome no cartão</label>
                                <input type="text" id="card-holder" name="card_holder" autocomplete="cc-name" placeholder="Nome no cartão" maxlength="120" aria-label="Nome no cartão">
                            </div>
                            <?php
                            $cardYearStart = (int) date('Y');
                            $cardYearEnd = $cardYearStart + 80;
                            $cardMonthNames = [
                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                            ];
                            ?>
                            <div class="checkout-card-row checkout-card-row--triple">
                                <div class="checkout-field">
                                    <label for="card-exp-month" class="visually-hidden">Mês de validade</label>
                                    <select id="card-exp-month" name="card_exp_month" autocomplete="cc-exp-month" aria-label="Mês de validade">
                                        <option value="">Mês</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?> — <?= $cardMonthNames[$m] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="checkout-field">
                                    <label for="card-exp-year" class="visually-hidden">Ano de validade</label>
                                    <select id="card-exp-year" name="card_exp_year" autocomplete="cc-exp-year" aria-label="Ano de validade">
                                        <option value="">Ano</option>
                                        <?php for ($y = $cardYearStart; $y <= $cardYearEnd; $y++): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="checkout-field checkout-field--card-cvv">
                                    <label for="card-cvv" class="visually-hidden">CVV</label>
                                    <input type="text" id="card-cvv" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="CVV" maxlength="4" aria-label="CVV">
                                    <span class="checkout-card-input-icon checkout-card-input-icon--cvv" aria-hidden="true">
                                        <svg width="20" height="14" viewBox="0 0 28 18" fill="none"><rect x="0.5" y="0.5" width="27" height="17" rx="2" stroke="currentColor" stroke-width="1"/><rect x="4" y="3" width="20" height="3" fill="currentColor" opacity="0.35"/><rect x="16" y="9" width="8" height="5" rx="1" fill="#ef4444" opacity="0.9"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="checkout-card-secure">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="1.75"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Pagamento seguro — apenas os 4 últimos dígitos são armazenados.
                        </p>
                    </div>
                </section>
                <div class="checkout-form-footer">
                    <button type="submit" class="btn btn-primary checkout-submit">
                        <span>Finalizar pedido</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <p class="checkout-form-footer-hint">Ao continuar, seu pedido será registrado e você segue para o pagamento.</p>
                </div>
            </form>
            <div id="payment-area" class="checkout-payment-area hidden" aria-live="polite">
                <div class="checkout-payment-divider" aria-hidden="true"></div>
                <div class="checkout-payment-head">
                    <span id="checkout-payment-badge" class="checkout-payment-badge checkout-payment-badge--pix" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M4 7h16v10H4V7z" stroke="currentColor" stroke-width="1.75"/><path d="M7 10.5h4M7 13.5h2.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                        PIX
                    </span>
                    <h2 id="checkout-payment-title" class="checkout-payment-title">Pagamento</h2>
                    <p id="checkout-payment-desc" class="checkout-payment-desc">Aguarde as instruções de pagamento.</p>
                </div>
                <ol class="checkout-payment-steps" aria-label="Como pagar">
                    <li><span class="checkout-payment-step-num">1</span> Abra o app do seu banco</li>
                    <li><span class="checkout-payment-step-num">2</span> Escolha pagar com PIX (QR Code)</li>
                    <li><span class="checkout-payment-step-num">3</span> Escaneie o código abaixo</li>
                </ol>
                <div id="pix-qr-container" class="checkout-pix-qr"></div>
                <div class="checkout-payment-foot">
                    <div class="checkout-payment-status-wrap is-waiting" id="checkout-payment-status-wrap">
                        <span class="checkout-payment-spinner" aria-hidden="true"></span>
                        <p id="payment-status" class="checkout-payment-status">Aguardando pagamento...</p>
                    </div>
                    <p class="checkout-payment-hint">A confirmação é automática assim que o pagamento for identificado.</p>
                </div>
            </div>
        </div>
        <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="checkout-back" id="checkout-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Voltar ao carrinho
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$baseUrl = rtrim(base_url(), '/');
$extra_js = "
<script>window.BASE_URL = " . json_encode($baseUrl) . "; const storeSlug = " . json_encode($store['slug']) . "; const storeId = " . (int) $store['id'] . "; const cartData = " . json_encode(array_map(static function ($item) {
    return [
        'product_id' => $item['product']['id'],
        'quantity' => $item['quantity'],
        'variant_key' => $item['variant_key'],
    ];
}, $cartItems)) . ";</script>
<script src=\"" . asset('js/cep-lookup.js') . "\"></script>
<script src=\"" . asset('js/checkout.js') . "\"></script>
";
require __DIR__ . '/layout_store.php';
