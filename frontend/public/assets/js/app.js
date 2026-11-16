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

  function storeCartLineKey(productId, variantKey) {
    var pid = String(productId);
    var vk = variantKey ? String(variantKey).trim() : '';
    if (!vk) return pid;
    return pid + '#' + vk;
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.add-to-cart');
    if (!btn) return;
    const storeId = btn.dataset.storeId;
    const productId = btn.dataset.productId;
    const requireVariant = btn.dataset.requireVariant === '1';
    const variantKey = (btn.dataset.variantKey || '').trim();
    if (requireVariant && !variantKey) {
      showStoreToast('Escolha a cor e o tamanho antes de adicionar');
      return;
    }
    let qty = 1;
    const qtyEl = document.getElementById('qty');
    if (qtyEl) qty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
    const pedido = qty;
    const max = parseInt(btn.dataset.max, 10) || 0;
    if (!storeId || !productId) return;
    const lineKey = storeCartLineKey(productId, variantKey);
    let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
    if (!cart[storeId]) cart[storeId] = {};
    const noCarrinho = parseInt(cart[storeId][lineKey], 10) || 0;
    // O limite vale para o total da linha, somando o que já estava no
    // carrinho. Antes só a quantidade nova era limitada, e adicionar duas
    // vezes passava do estoque; o corte também acontecia sem aviso.
    let total = noCarrinho + qty;
    if (max > 0 && total > max) total = max;
    const adicionados = total - noCarrinho;
    if (adicionados <= 0) {
      const msgCheio = 'Você já tem as ' + max + ' unidades disponíveis no carrinho';
      if (window.StoreUI) window.StoreUI.notify(msgCheio, window.StoreUI.cartUrl ? { href: window.StoreUI.cartUrl, label: 'Ver carrinho' } : null);
      else showStoreToast(msgCheio);
      return;
    }
    cart[storeId][lineKey] = total;
    sessionStorage.setItem('cart', JSON.stringify(cart));
    if (typeof window.syncCartToSession === 'function') window.syncCartToSession();
    let msg = 'Adicionado ao carrinho';
    if (adicionados < pedido) {
      msg = max === 1 ? 'Só 1 disponível: adicionamos 1' : 'Só ' + max + ' disponíveis: seu carrinho ficou com ' + total;
      if (qtyEl) qtyEl.value = String(Math.max(1, Math.min(parseInt(qtyEl.value, 10) || 1, max)));
    }
    // Na loja, o aviso traz "Ver carrinho" e o ícone do cabeçalho ganha o número.
    if (window.StoreUI) window.StoreUI.cartAdded(msg);
    else showStoreToast(msg);
  });

  /**
   * Sair da conta apaga também o carrinho guardado no navegador. O servidor
   * já apagava o dele no logout, mas a cópia do sessionStorage voltava na
   * visita seguinte ao carrinho: num computador compartilhado, a próxima
   * pessoa via (e podia comprar) o que a anterior escolheu.
   */
  document.addEventListener('click', function (e) {
    const link = e.target.closest && e.target.closest('a[href]');
    if (!link) return;
    let path = '';
    try { path = new URL(link.href, window.location.href).pathname; } catch (err) { return; }
    if (!/\/sair\/?$/.test(path)) return;
    try { sessionStorage.removeItem('cart'); } catch (err) { /* navegador sem storage: nada a limpar */ }
  }, true); // captura: o menu "Minha conta" interrompe a propagação do clique.

  document.querySelectorAll('.remove-from-cart').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const cartKey = this.dataset.cartKey;
      const storeId = document.querySelector('[data-store-id]')?.dataset?.storeId;
      if (!storeId || !cartKey) return;
      let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
      if (cart[storeId]) delete cart[storeId][cartKey];
      sessionStorage.setItem('cart', JSON.stringify(cart));
      if (typeof window.syncCartToSessionAndReload === 'function') window.syncCartToSessionAndReload();
      else location.reload();
    });
  });

  function applyCartQty(cartKey, storeId, newQty) {
    let cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
    if (!cart[storeId]) cart[storeId] = {};
    if (newQty <= 0) delete cart[storeId][cartKey];
    else cart[storeId][cartKey] = newQty;
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
    const cartKey = row?.dataset?.cartKey;
    const storeId = document.querySelector('[data-store-id]')?.dataset?.storeId;
    if (!storeId || !cartKey) return;
    const valueEl = control.querySelector('.cart-qty-value');
    const max = parseInt(control.getAttribute('data-max'), 10) || 9999;
    control.querySelector('.cart-qty-minus')?.addEventListener('click', function () {
      let v = parseInt(valueEl.textContent, 10) || 1;
      if (v <= 1) return;
      v--;
      valueEl.textContent = v;
      applyCartQty(cartKey, storeId, v);
    });
    control.querySelector('.cart-qty-plus')?.addEventListener('click', function () {
      let v = parseInt(valueEl.textContent, 10) || 0;
      if (v >= max) return;
      v++;
      valueEl.textContent = v;
      applyCartQty(cartKey, storeId, v);
    });
  });
})();

/* Ordenação da vitrine: troca ?ordem= na URL e recarrega a lista. */
(function () {
  document.addEventListener('change', function (e) {
    var sel = e.target.closest && e.target.closest('.js-store-sort');
    if (!sel) return;
    var url = new URL(window.location.href);
    if (sel.value && sel.value !== 'mais-vendidos') url.searchParams.set('ordem', sel.value);
    else url.searchParams.delete('ordem');
    window.location.href = url.toString();
  });
})();
