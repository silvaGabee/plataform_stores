<?php
$cartItems = [];
$productRepo = new \App\Repositories\ProductRepository();
foreach ($cart as $productId => $qty) {
    $p = $productRepo->findByIdAndStore($productId, $store['id']);
    if ($p) $cartItems[] = ['product' => $p, 'quantity' => $qty];
}
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['product']['sale_price'] * $item['quantity'];
}
$content = ob_start();
?>
<div class="container cart-page" data-store-id="<?= (int) $store['id'] ?>">
    <h1>Carrinho</h1>
    <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-secondary cart-back-btn">← Continuar comprando</a>
    <?php if (empty($cartItems)): ?>
        <p>Carrinho vazio.</p>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr><th>Produto</th><th>Qtd</th><th>Preço</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): 
                    $p = $item['product'];
                    $sub = $p['sale_price'] * $item['quantity'];
                ?>
                    <tr data-product-id="<?= $p['id'] ?>">
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>
                            <div class="cart-qty-control" data-max="<?= (int) $p['stock_quantity'] ?>">
                                <button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Diminuir">−</button>
                                <span class="cart-qty-value"><?= $item['quantity'] ?></span>
                                <button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Aumentar">+</button>
                            </div>
                        </td>
                        <td>R$ <?= number_format($p['sale_price'], 2, ',', '.') ?></td>
                        <td class="subtotal">R$ <?= number_format($sub, 2, ',', '.') ?></td>
                        <td><button type="button" class="btn btn-sm remove-from-cart" data-product-id="<?= $p['id'] ?>">Remover</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="cart-total"><strong>Total: R$ <?= number_format($total, 2, ',', '.') ?></strong></p>
        <a href="<?= base_url("loja/{$store['slug']}/checkout") ?>" class="btn btn-primary">Finalizar compra</a>
    <?php endif; ?>
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
