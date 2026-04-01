(function () {
  const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

  function getCookie(name) {
    const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
    return v ? v[2] : null;
  }

  /** Aviso temporário na loja (some sozinho, sem botão OK). */
  function showStoreToast(message, durationMs) {
    const ms = durationMs === undefined ? 2600 : durationMs;
    let el = document.getElementById('store-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'store-toast';
      el.className = 'store-toast';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.remove('is-visible');
    void el.offsetHeight;
    clearTimeout(showStoreToast._hideTimer);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        el.classList.add('is-visible');
      });
    });
    showStoreToast._hideTimer = setTimeout(function () {
      el.classList.remove('is-visible');
    }, ms);
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
      showStoreToast('Adicionado ao carrinho');
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

  (function authModal() {
    var modal = document.getElementById('auth-modal');
    if (!modal) return;

    var initial = document.body.getAttribute('data-auth-modal-initial') || '';
    var openers = document.querySelectorAll('.js-auth-open');
    var closers = modal.querySelectorAll('.js-auth-close');
    var tabButtons = modal.querySelectorAll('.auth-modal-tab');
    var panelLogin = document.getElementById('panel-login');
    var panelCadastro = document.getElementById('panel-cadastro');

    function setTab(which) {
      var w = which === 'cadastro' ? 'cadastro' : 'login';
      tabButtons.forEach(function (t) {
        var on = t.getAttribute('data-auth-tab') === w;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      if (panelLogin) {
        panelLogin.classList.toggle('is-active', w === 'login');
        panelLogin.hidden = w !== 'login';
      }
      if (panelCadastro) {
        panelCadastro.classList.toggle('is-active', w === 'cadastro');
        panelCadastro.hidden = w !== 'cadastro';
      }
    }

    function open(which) {
      setTab(which);
      modal.removeAttribute('hidden');
      requestAnimationFrame(function () {
        modal.classList.add('is-open');
      });
      document.body.classList.add('auth-modal-open');
      var sel = which === 'cadastro' ? '#modal-register-name' : '#modal-login-email';
      var focusTarget = modal.querySelector(sel);
      if (focusTarget) setTimeout(function () { focusTarget.focus(); }, 80);
    }

    function closeModal() {
      modal.classList.remove('is-open');
      document.body.classList.remove('auth-modal-open');
      setTimeout(function () {
        modal.setAttribute('hidden', '');
      }, 220);
    }

    openers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        open(btn.getAttribute('data-auth-tab') || 'login');
      });
    });
    closers.forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    tabButtons.forEach(function (t) {
      t.addEventListener('click', function () {
        setTab(t.getAttribute('data-auth-tab') || 'login');
        var w = t.getAttribute('data-auth-tab') === 'cadastro' ? 'cadastro' : 'login';
        var inp = modal.querySelector(w === 'cadastro' ? '#modal-register-name' : '#modal-login-email');
        if (inp) inp.focus();
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });

    if (initial === 'login' || initial === 'cadastro') {
      open(initial);
    }
  })();

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
