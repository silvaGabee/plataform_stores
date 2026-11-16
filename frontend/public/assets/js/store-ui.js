/**
 * Vitrine: comportamentos do cabeçalho e de elementos fixos, em todas as
 * páginas da loja.
 *
 * - Menu "Minha conta": abre/fecha no clique, fecha com Esc ou clique fora.
 * - Contador do ícone do carrinho.
 * - Aviso "Adicionado ao carrinho" com o atalho "Ver carrinho".
 * - Botão flutuante do WhatsApp: sai da frente quando há um controle embaixo.
 */
(function () {
  'use strict';

  var body = document.body;
  var storeId = body.getAttribute('data-store-id');
  var slug = body.getAttribute('data-store-slug-ui') || '';
  var base = ((document.querySelector('meta[name="base-url"]') || {}).content || '').replace(/\/$/, '');

  // ------------------------------------------------------------ menu da conta
  (function userMenu() {
    var btn = document.querySelector('.store-user-btn');
    var popup = document.getElementById('store-user-popup');
    if (!btn || !popup) return;

    function isOpen() { return !popup.classList.contains('hidden'); }
    function open() {
      // No celular o menu é fixo na tela (vitrine.css): abre logo abaixo do botão.
      popup.style.setProperty('--store-user-popup-top', btn.getBoundingClientRect().bottom + 'px');
      popup.classList.remove('hidden');
      btn.setAttribute('aria-expanded', 'true');
    }
    function close(focusBtn) {
      if (!isOpen()) return;
      popup.classList.add('hidden');
      btn.setAttribute('aria-expanded', 'false');
      if (focusBtn) btn.focus();
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) close(false); else open();
    });
    popup.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { close(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) {
        e.preventDefault();
        close(true);
      }
    });
    // Tab para fora do menu também fecha, para ele não ficar aberto atrás do foco.
    popup.addEventListener('focusout', function (e) {
      if (e.relatedTarget && !popup.contains(e.relatedTarget) && e.relatedTarget !== btn) close(false);
    });
  })();

  // ------------------------------------------------------------ contador do carrinho
  /**
   * O carrinho vive no sessionStorage do navegador e é copiado para a sessão
   * do servidor ao abrir o carrinho. O servidor manda o número inicial; se o
   * navegador tiver o carrinho desta loja, ele é a fonte mais recente.
   */
  function cartCountFromStorage() {
    try {
      var all = JSON.parse(sessionStorage.getItem('cart') || '{}');
      if (!storeId || !Object.prototype.hasOwnProperty.call(all, storeId)) return null;
      var lines = all[storeId] || {};
      var total = 0;
      Object.keys(lines).forEach(function (k) {
        var v = lines[k];
        total += typeof v === 'object' && v ? (parseInt(v.quantity, 10) || 0) : (parseInt(v, 10) || 0);
      });
      return total;
    } catch (e) {
      return null;
    }
  }

  function renderCartCount() {
    var badge = document.querySelector('[data-cart-count]');
    if (!badge) return;
    var n = cartCountFromStorage();
    if (n === null) n = parseInt(badge.textContent, 10) || 0;
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.classList.toggle('hidden', n <= 0);
    var link = badge.closest('a');
    if (link) link.setAttribute('aria-label', n > 0 ? 'Carrinho, ' + n + (n === 1 ? ' item' : ' itens') : 'Carrinho');
  }
  renderCartCount();
  document.addEventListener('store:cart-changed', function () {
    renderCartCount();
    var badge = document.querySelector('[data-cart-count]');
    if (badge && !badge.classList.contains('hidden')) {
      badge.classList.remove('is-bump');
      void badge.offsetWidth;
      badge.classList.add('is-bump');
    }
  });

  // ------------------------------------------------------------ aviso do carrinho
  var toastTimer = null;
  /** Aviso no canto, com link opcional. Não cobre o conteúdo do produto. */
  function notify(message, action) {
    var el = document.getElementById('store-notice');
    if (!el) {
      el = document.createElement('div');
      el.id = 'store-notice';
      el.className = 'store-notice';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      body.appendChild(el);
    }
    el.innerHTML = '';
    var icon = document.createElement('span');
    icon.className = 'store-notice-icon';
    icon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
    var text = document.createElement('span');
    text.className = 'store-notice-text';
    text.textContent = message;
    el.appendChild(icon);
    el.appendChild(text);
    if (action && action.href) {
      var a = document.createElement('a');
      a.className = 'store-notice-action';
      a.href = action.href;
      a.textContent = action.label;
      el.appendChild(a);
    }
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'store-notice-close';
    close.setAttribute('aria-label', 'Fechar aviso');
    close.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';
    close.addEventListener('click', hide);
    el.appendChild(close);

    function hide() { el.classList.remove('is-visible'); }
    el.classList.remove('is-visible');
    void el.offsetWidth;
    el.classList.add('is-visible');
    clearTimeout(toastTimer);
    // Com link, fica mais tempo: dá tempo de ler e tocar em "Ver carrinho".
    toastTimer = setTimeout(hide, action ? 6000 : 3000);
    el.onmouseenter = function () { clearTimeout(toastTimer); };
    el.onmouseleave = function () { toastTimer = setTimeout(hide, 2500); };
  }

  window.StoreUI = {
    notify: notify,
    cartAdded: function () {
      document.dispatchEvent(new CustomEvent('store:cart-changed'));
      notify('Adicionado ao carrinho', slug ? { href: base + '/loja/' + encodeURIComponent(slug) + '/carrinho', label: 'Ver carrinho' } : null);
    },
    cartUrl: slug ? base + '/loja/' + encodeURIComponent(slug) + '/carrinho' : null,
    entrarUrl: function (volta) {
      return base + '/loja/' + encodeURIComponent(slug) + '/entrar' + (volta ? '?volta=' + encodeURIComponent(volta) : '');
    }
  };

  // ------------------------------------------------------------ WhatsApp flutuante
  /**
   * O botão fica fixo no canto e passava por cima do "Ordenar por", de campos
   * e de botões. Quando há um controle embaixo dele, ele some (fica
   * transparente e não recebe clique) até o controle sair dali.
   */
  (function whatsappFab() {
    var fab = document.querySelector('.store-whatsapp-fab');
    if (!fab || !document.elementsFromPoint) return;
    var CONTROLS = 'select, input, textarea, button, .btn, .ui-sort, [data-fab-avoid], .store-footer a';
    var ticking = false;

    function overlapsControl() {
      var r = fab.getBoundingClientRect();
      if (!r.width) return false;
      var pts = [
        [r.left + r.width / 2, r.top + r.height / 2],
        [r.left + 6, r.top + 6],
        [r.right - 6, r.top + 6],
        [r.left + 6, r.bottom - 6],
        [r.right - 6, r.bottom - 6]
      ];
      for (var i = 0; i < pts.length; i++) {
        var els = document.elementsFromPoint(pts[i][0], pts[i][1]);
        for (var j = 0; j < els.length; j++) {
          var el = els[j];
          if (el === fab || fab.contains(el)) continue;
          // O card do produto inteiro é um link; o que importa são os controles.
          if (el.closest && el.closest(CONTROLS) && !el.closest('.store-whatsapp-fab')) return true;
        }
      }
      return false;
    }

    function update() {
      ticking = false;
      fab.classList.toggle('is-dodging', overlapsControl());
    }
    function schedule() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(update);
    }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    document.addEventListener('store:layout-changed', schedule);
    setTimeout(update, 300);
  })();
})();
