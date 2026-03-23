(function () {
  const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

  function getCookie(name) {
    const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
    return v ? v[2] : null;
  }

  document.querySelectorAll('.add-to-cart').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const storeId = this.dataset.storeId;
      const productId = this.dataset.productId;
      const name = this.dataset.name;
      const price = this.dataset.price;
      let qty = 1;
      const qtyEl = document.getElementById('qty');
      if (qtyEl) qty = parseInt(qtyEl.value, 10) || 1;
      const max = this.dataset.max;
      if (max && qty > parseInt(max, 10)) qty = parseInt(max, 10);
      if (!storeId || !productId) return;
      let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
      if (!cart[storeId]) cart[storeId] = {};
      cart[storeId][productId] = (cart[storeId][productId] || 0) + qty;
      sessionStorage.setItem('cart', JSON.stringify(cart));
      if (typeof window.syncCartToSession === 'function') window.syncCartToSession();
      alert('Adicionado ao carrinho');
    });
  });

  document.querySelectorAll('.remove-from-cart').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const productId = this.dataset.productId;
      const storeId = document.querySelector('[data-store-id]')?.dataset?.storeId;
      if (!storeId) return;
      let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
      if (cart[storeId]) delete cart[storeId][productId];
      sessionStorage.setItem('cart', JSON.stringify(cart));
      if (typeof window.syncCartToSessionAndReload === 'function') window.syncCartToSessionAndReload();
      else location.reload();
    });
  });

  function applyCartQty(productId, storeId, newQty) {
    let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
    if (!cart[storeId]) cart[storeId] = {};
    if (newQty <= 0) delete cart[storeId][productId];
    else cart[storeId][productId] = newQty;
    sessionStorage.setItem('cart', JSON.stringify(cart));
    if (typeof window.syncCartToSessionAndReload === 'function') window.syncCartToSessionAndReload();
    else location.reload();
  }

  document.querySelectorAll('.cart-qty-control').forEach(function (control) {
    const row = control.closest('tr');
    const productId = row?.dataset?.productId;
    const storeId = document.querySelector('[data-store-id]')?.dataset?.storeId;
    if (!storeId || !productId) return;
    const valueEl = control.querySelector('.cart-qty-value');
    const max = parseInt(control.getAttribute('data-max'), 10) || 9999;
    control.querySelector('.cart-qty-minus')?.addEventListener('click', function () {
      let v = parseInt(valueEl.textContent, 10) || 1;
      if (v <= 1) return;
      v--;
      valueEl.textContent = v;
      applyCartQty(productId, storeId, v);
    });
    control.querySelector('.cart-qty-plus')?.addEventListener('click', function () {
      let v = parseInt(valueEl.textContent, 10) || 0;
      if (v >= max) return;
      v++;
      valueEl.textContent = v;
      applyCartQty(productId, storeId, v);
    });
  });
})();
