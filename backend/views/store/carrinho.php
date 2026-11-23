<?php
require __DIR__ . '/_cart_items_build.php';
$total = $cartTotal;
ob_start();
?>
<div class="store-subpage store-cart-page cart-page" data-store-id="<?= (int) $store['id'] ?>">
    <div class="store-subpage-inner container">
        <?php if (!empty($cartItems)): ?>
            <div class="store-subpage-top">
                <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-subpage-back-pill"><?= ui_icon('arrow-left', 16) ?> Continuar comprando</a>
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
                                $sub = product_effective_price($p) * $item['quantity'];
                                $__img = product_cover_image($p);
                                $__imgUrl = $__img['thumb_url'] ?? ($__img['url'] ?? null);
                                $__pUrl = base_url("loja/{$store['slug']}/produto/{$p['id']}");
                                ?>
                                <tr data-cart-key="<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>">
                                    <td data-label="Produto" class="cart-line-product">
                                        <a href="<?= htmlspecialchars($__pUrl) ?>" class="cart-line-thumb" tabindex="-1" aria-hidden="true">
                                            <?php if ($__imgUrl): ?>
                                                <img src="<?= htmlspecialchars($__imgUrl) ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <?= ui_icon('package', 22) ?>
                                            <?php endif; ?>
                                        </a>
                                        <span class="cart-line-info">
                                            <a href="<?= htmlspecialchars($__pUrl) ?>" class="cart-line-name"><?= htmlspecialchars($p['name']) ?></a>
                                            <?php if (!empty($item['variant_label'])): ?>
                                                <span class="cart-line-variant"><?= htmlspecialchars($item['variant_label']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td data-label="Qtd">
                                        <div class="cart-qty-control" data-max="<?= (int) $item['line_stock'] ?>">
                                            <button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Diminuir">−</button>
                                            <span class="cart-qty-value"><?= $item['quantity'] ?></span>
                                            <button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Aumentar">+</button>
                                        </div>
                                    </td>
                                    <td data-label="Preço"><?php if (product_on_sale($p)): ?><s class="cart-price-old">R$ <?= number_format((float) $p['sale_price'], 2, ',', '.') ?></s> <?php endif; ?>R$ <?= number_format(product_effective_price($p), 2, ',', '.') ?></td>
                                    <td class="subtotal" data-label="Subtotal">R$ <?= number_format($sub, 2, ',', '.') ?></td>
                                    <td class="store-cart-actions" data-label="">
                                        <button type="button" class="store-cart-remove remove-from-cart" data-cart-key="<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Remover <?= htmlspecialchars($p['name']) ?> do carrinho"><?= ui_icon('trash', 16) ?><span>Remover</span></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $__pixPct = \App\Services\OrderPricing::pixPct($store);
                $__pixTotal = \App\Services\OrderPricing::pixPrice($store, $total);
                $__waDuvida = store_whatsapp_url($store, 'Olá! Estou com uma dúvida sobre o meu carrinho na loja ' . $store['name'] . '.');
                ?>
                <div class="store-cart-summary">
                    <div class="store-cart-shipping" id="cart-shipping" data-slug="<?= htmlspecialchars($store['slug']) ?>" data-subtotal="<?= htmlspecialchars(number_format($total, 2, '.', '')) ?>" data-pix-pct="<?= htmlspecialchars((string) $__pixPct) ?>">
                        <label for="cart-cep" class="store-cart-shipping-label"><?= ui_icon('truck', 16) ?> Calcular frete</label>
                        <div class="store-cart-shipping-row">
                            <input type="text" id="cart-cep" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code" aria-describedby="cart-shipping-result">
                            <button type="button" class="btn btn-secondary btn-sm" id="cart-cep-btn">Calcular</button>
                        </div>
                        <p class="store-cart-shipping-result" id="cart-shipping-result" role="status" aria-live="polite"></p>
                    </div>
                    <div class="store-cart-totals">
                        <dl class="store-cart-lines">
                            <div><dt>Subtotal</dt><dd>R$ <?= number_format($total, 2, ',', '.') ?></dd></div>
                            <div><dt>Frete</dt><dd id="cart-total-shipping" class="store-cart-shipping-value">Calculado na próxima etapa</dd></div>
                        </dl>
                        <p class="cart-total"><span class="store-cart-total-label">Total</span> <strong class="store-cart-total-value" id="cart-total-value">R$ <?= number_format($total, 2, ',', '.') ?></strong></p>
                        <?php if ($__pixTotal !== null): ?>
                            <p class="store-cart-pix" id="cart-total-pix">ou <strong>R$ <?= number_format($__pixTotal, 2, ',', '.') ?></strong> no PIX</p>
                        <?php endif; ?>
                        <a href="<?= base_url("loja/{$store['slug']}/checkout") ?>" class="btn btn-primary store-cart-checkout-btn">Finalizar compra</a>
                        <?php if ($__waDuvida !== null): ?>
                            <p class="store-cart-help">Dúvidas? <a href="<?= htmlspecialchars($__waDuvida) ?>" target="_blank" rel="noopener">Fale com a loja no WhatsApp</a></p>
                        <?php endif; ?>
                    </div>
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
/*
 * O carrinho vive no sessionStorage (é dele que o ícone do cabeçalho conta) e
 * é copiado para a sessão do servidor, que monta esta página.
 *
 * Antes a página seguia para ?synced=1 mesmo quando a cópia falhava, e o
 * servidor descartava em silêncio produto apagado: a página dizia "carrinho
 * vazio" e o ícone seguia com número. Agora:
 *  - deu certo: o navegador recebe o carrinho que o servidor guardou (ícone =
 *    página) e, se algo saiu, um aviso aparece depois de recarregar;
 *  - falhou: a página não sai do lugar, mostra o erro com "Tentar de novo" e
 *    não mexe no carrinho do navegador.
 */
(function(){
  var storeId = <?= (int)$store['id'] ?>;
  var base = '<?= rtrim(base_url(), '/') ?>';
  var slug = '<?= htmlspecialchars($store['slug'], ENT_QUOTES, 'UTF-8') ?>';
  var checkoutUrl = '<?= base_url("loja/{$store['slug']}/checkout") ?>';
  var AVISO = 'cart_notice';

  function lerCarrinho() {
    try { return JSON.parse(sessionStorage.getItem('cart') || '{}')[storeId]; } catch (e) { return null; }
  }

  function gravarCarrinho(linhas) {
    try {
      var todos = JSON.parse(sessionStorage.getItem('cart') || '{}');
      todos[storeId] = linhas || {};
      sessionStorage.setItem('cart', JSON.stringify(todos));
    } catch (e) { /* sem storage: o servidor continua com o carrinho certo */ }
    document.dispatchEvent(new CustomEvent('store:cart-changed'));
  }

  function mostrar(texto, tipo, tentarDeNovo) {
    var caixa = document.getElementById('cart-sync-msg');
    if (!caixa) {
      caixa = document.createElement('div');
      caixa.id = 'cart-sync-msg';
      var alvo = document.querySelector('.store-subpage-inner');
      if (alvo) alvo.insertBefore(caixa, alvo.firstChild);
      else return;
    }
    caixa.className = 'alert cart-sync-msg ' + (tipo === 'erro' ? 'alert-error' : 'cart-sync-msg--aviso');
    caixa.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
    caixa.textContent = texto;
    if (tentarDeNovo) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-secondary btn-sm cart-sync-retry';
      b.textContent = 'Tentar de novo';
      b.addEventListener('click', tentarDeNovo);
      caixa.appendChild(document.createTextNode(' '));
      caixa.appendChild(b);
    }
  }

  /** Copia o carrinho para o servidor. Resolve com a resposta; rejeita com a mensagem de erro. */
  function sincronizar(linhas) {
    return fetch(base + '/api/loja/' + slug + '/cart/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: linhas || {} })
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || !d || !d.success) {
          throw new Error((d && d.error) || 'Não foi possível atualizar o carrinho.');
        }
        return d;
      });
    }, function () {
      throw new Error('Sem conexão com a loja. Confira a internet.');
    });
  }

  /** Sincroniza e, só se der certo, alinha o navegador ao servidor e segue para `destino`. */
  function sincronizarESeguir(destino) {
    sincronizar(lerCarrinho()).then(function (d) {
      gravarCarrinho(d.cart);
      var partes = [];
      if (d.removidos > 0) partes.push(d.removidos === 1 ? '1 produto que não está mais à venda saiu do carrinho' : d.removidos + ' produtos que não estão mais à venda saíram do carrinho');
      if (d.cortados > 0) partes.push(d.cortados === 1 ? '1 item ficou de fora (o limite é de 100 itens diferentes)' : d.cortados + ' itens ficaram de fora (o limite é de 100 itens diferentes)');
      if (partes.length) {
        try { sessionStorage.setItem(AVISO, partes.join('; ') + '.'); } catch (e) {}
      }
      destino();
    }, function (erro) {
      mostrar(erro.message + ' Seu carrinho foi mantido.', 'erro', function () { sincronizarESeguir(destino); });
    });
  }

  // Aviso guardado antes de recarregar (ex.: produto que saiu do carrinho).
  try {
    var aviso = sessionStorage.getItem(AVISO);
    if (aviso) {
      sessionStorage.removeItem(AVISO);
      mostrar(aviso.charAt(0).toUpperCase() + aviso.slice(1), 'aviso');
    }
  } catch (e) {}

  var hasSynced = window.location.search.indexOf('synced=1') !== -1;
  var cart = lerCarrinho();
  if (cart && Object.keys(cart).length && !hasSynced) {
    sincronizarESeguir(function () { window.location.href = window.location.pathname + '?synced=1'; });
    return;
  }
  window.syncCartToSessionAndReload = function () {
    sincronizarESeguir(function () { location.reload(); });
  };
  var btn = document.querySelector('a.btn-primary[href*="checkout"]');
  if (btn) btn.addEventListener('click', function (e) {
    e.preventDefault();
    sincronizarESeguir(function () { window.location.href = checkoutUrl; });
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
