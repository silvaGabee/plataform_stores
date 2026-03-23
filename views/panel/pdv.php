<?php $content = ob_start(); ?>
<div class="panel-content pdv-page">
    <header class="pdv-header">
        <h1 class="pdv-title">PDV</h1>
        <div class="pdv-cash-status" id="cash-status">
            <p>Carregando...</p>
        </div>
        <p class="pdv-greeting hidden" id="pdv-greeting" aria-live="polite"></p>
    </header>
    <div class="pdv-grid">
        <section class="pdv-products">
            <div class="pdv-products-header">
                <h2>Produtos</h2>
                <input type="search" id="pdv-search" class="pdv-search" placeholder="Buscar produto..." autocomplete="off">
            </div>
            <div id="pdv-product-list" class="pdv-product-list"></div>
        </section>
        <aside class="pdv-cart">
            <h2>Venda atual</h2>
            <div class="pdv-cart-items-wrap">
                <ul id="pdv-cart-items" class="pdv-cart-items"></ul>
                <p id="pdv-cart-empty" class="pdv-cart-empty">Nenhum item na venda.</p>
            </div>
            <div class="pdv-cart-total-wrap">
                <span class="pdv-cart-total-label">Total</span>
                <span class="pdv-cart-total-value">R$ <span id="pdv-total">0,00</span></span>
            </div>
            <div class="pdv-cart-cliente">
                <label for="pdv-customer-name">Cliente (nome para o pedido)</label>
                <input type="text" id="pdv-customer-name" placeholder="Cliente PDV">
            </div>
            <button type="button" class="btn btn-primary pdv-btn-finish" id="pdv-finish">Finalizar venda</button>
        </aside>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . '; const pdvUserName = ' . json_encode($pdv_user_name ?? '') . ';</script><script src="' . asset('js/panel-pdv.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
