<?php
$cartItems = [];
$productRepo = new \App\Repositories\ProductRepository();
foreach ($cart as $productId => $qty) {
    $p = $productRepo->findByIdAndStore($productId, $store['id']);
    if ($p) {
        $cartItems[] = ['product' => $p, 'quantity' => $qty];
    }
}
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['product']['sale_price'] * $item['quantity'];
}
ob_start();
?>
<div class="store-subpage store-cart-page cart-page" data-store-id="<?= (int) $store['id'] ?>">
    <div class="store-subpage-inner container">
        <?php if (!empty($cartItems)): ?>
            <div class="store-subpage-top">
                <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-subpage-back-pill">Continuar comprando</a>
            </div>
            <header class="store-subpage-head">
                <h1 class="store-subpage-title">Carrinho</h1>
                <p class="store-subpage-lead">Revise quantidades e valores antes de finalizar a compra.</p>
            </header>
            <div class="store-cart-filled">
                <div class="store-cart-table-wrap">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th scope="col">Produto</th>
                                <th scope="col">Qtd</th>
                                <th scope="col">Preço</th>
                                <th scope="col">Subtotal</th>
                                <th scope="col"><span class="visually-hidden">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item):
                                $p = $item['product'];
                                $sub = $p['sale_price'] * $item['quantity'];
                                ?>
                                <tr data-product-id="<?= $p['id'] ?>">
                                    <td data-label="Produto"><?= htmlspecialchars($p['name']) ?></td>
                                    <td data-label="Qtd">
                                        <div class="cart-qty-control" data-max="<?= (int) $p['stock_quantity'] ?>">
                                            <button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Diminuir">−</button>
                                            <span class="cart-qty-value"><?= $item['quantity'] ?></span>
                                            <button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Aumentar">+</button>
                                        </div>
                                    </td>
                                    <td data-label="Preço">R$ <?= number_format($p['sale_price'], 2, ',', '.') ?></td>
                                    <td class="subtotal" data-label="Subtotal">R$ <?= number_format($sub, 2, ',', '.') ?></td>
                                    <td class="store-cart-actions" data-label="">
                                        <button type="button" class="btn btn-sm btn-danger remove-from-cart" data-product-id="<?= $p['id'] ?>">Remover</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="store-cart-summary">
                    <p class="cart-total"><span class="store-cart-total-label">Total</span> <strong class="store-cart-total-value">R$ <?= number_format($total, 2, ',', '.') ?></strong></p>
                    <a href="<?= base_url("loja/{$store['slug']}/checkout") ?>" class="btn btn-primary store-cart-checkout-btn">Finalizar compra</a>
                </div>
            </div>
        <?php else: ?>
            <div class="store-cart-empty-layout">
                <header class="store-subpage-head">
                    <h1 class="store-subpage-title">Carrinho</h1>
                </header>
                <div class="store-empty-state store-cart-empty-state" role="status">
                    <div class="store-empty-state-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="9" cy="21" r="1" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="20" cy="21" r="1" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2 class="store-empty-state-title">Seu carrinho está vazio</h2>
                    <p class="store-empty-state-text">Explore a vitrine da loja, adicione produtos e eles aparecerão aqui com preços e quantidades atualizados.</p>
                    <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-primary store-cart-empty-cta">Continuar comprando</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
  var storeId = <?= (int)$store['id'] ?>;
  var base = '<?= rtrim(base_url(), '/') ?>';
  var slug = '<?= htmlspecialchars($store['slug'], ENT_QUOTES, 'UTF-8') ?>';
  var hasSynced = window.location.search.indexOf('synced=1') !== -1;
  var cart = JSON.parse(sessionStorage.getItem('cart') || '{}')[storeId];
  if (cart && Object.keys(cart).length && !hasSynced) {
    fetch(base + '/api/loja/' + slug + '/cart/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: cart })
    }).then(function() { window.location.href = window.location.pathname + '?synced=1'; });
    return;
  }
  window.syncCartToSessionAndReload = function() {
    var c = JSON.parse(sessionStorage.getItem('cart') || '{}')[storeId];
    fetch(base + '/api/loja/' + slug + '/cart/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: c || {} })
    }).then(function() { location.reload(); });
  };
  var btn = document.querySelector('a.btn-primary[href*="checkout"]');
  if (btn) btn.addEventListener('click', function(e) {
    e.preventDefault();
    var c = JSON.parse(sessionStorage.getItem('cart') || '{}')[storeId];
    fetch(base + '/api/loja/' + slug + '/cart/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: c || {} })
    }).then(function() { window.location.href = '<?= base_url("loja/{$store['slug']}/checkout") ?>'; });
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
