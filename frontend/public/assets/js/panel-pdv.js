/**
 * PDV — frente de caixa.
 *
 * Fluxo de uma venda:
 *   produto (ou variação) → carrinho → desconto opcional → forma de pagamento
 *   → pedido (order_type = pdv) → pagamento → confirmação → cupom.
 *
 * O servidor recalcula preço, estoque e desconto; aqui os valores servem só
 * para mostrar ao operador. O carrinho é guardado no navegador para não se
 * perder num F5 acidental no meio do atendimento.
 */
(function () {
  'use strict';

  var CFG = window.PDV_CONFIG;
  if (!CFG || !CFG.slug) return;

  var BASE = ((document.querySelector('meta[name="base-url"]') || {}).content || '').replace(/\/$/, '');
  var API = BASE + '/api/loja/' + encodeURIComponent(CFG.slug);
  var CART_KEY = 'pdv:cart:' + CFG.slug;
  var METHOD_LABEL = { dinheiro: 'Dinheiro', cartao: 'Cartão', pix: 'PIX' };

  var state = {
    products: [],
    categories: [],
    category: 0,
    search: '',
    onlyStock: true,
    cart: [],
    discount: { mode: 'value', value: 0 },
    customer: '',
    register: null,   // resumo do caixa aberto (null = fechado)
    busy: false
  };

  var $ = function (id) { return document.getElementById(id); };
  var app = $('pdv-app');

  /* ───────────────────────── utilidades ───────────────────────── */

  var brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  function money(n) { return brl.format(Number(n) || 0); }
  function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }

  /** Aceita "1.234,56", "1234,56", "1234.56" e "R$ 12". */
  function parseMoney(str) {
    var s = String(str == null ? '' : str).replace(/[^\d.,-]/g, '');
    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
    var n = parseFloat(s);
    return isNaN(n) ? 0 : round2(n);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function initials(name) {
    var parts = String(name || '?').trim().split(/\s+/);
    return ((parts[0] || '')[0] || '?').toUpperCase() + ((parts[1] || '')[0] || '').toUpperCase();
  }

  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, credentials: 'same-origin' };
    if (opts.body !== undefined) init.body = JSON.stringify(opts.body);
    return fetch(API + path, init).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok || (data && data.error)) {
          var err = new Error((data && data.error) || 'Erro de comunicação (' + r.status + ').');
          err.status = r.status;
          throw err;
        }
        return data;
      });
    });
  }

  function icon(d, size) {
    size = size || 18;
    return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="' + d + '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }
  var ICON = {
    minus: 'M5 12h14',
    plus: 'M12 5v14 M5 12h14',
    trash: 'M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6',
    cash: 'M2 6h20v12H2z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M6 12h.01 M18 12h.01',
    card: 'M2 5h20v14H2z M2 10h20 M6 15h4',
    pix: 'M12 2l4 4-4 4-4-4z M18 8l4 4-4 4-4-4z M6 8l4 4-4 4-4-4z M12 14l4 4-4 4-4-4z',
    check: 'M20 6L9 17l-5-5',
    print: 'M6 9V2h12v7 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v8H6z',
    in: 'M12 5v14 M19 12l-7 7-7-7',
    out: 'M12 19V5 M5 12l7-7 7 7',
    list: 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01',
    lock: 'M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4',
    copy: 'M9 9h11v11H9z M5 15H4V4h11v1'
  };

  /* ───────────────────────── toasts ───────────────────────── */

  function toast(msg, type) {
    var box = $('pdv-toasts');
    var el = document.createElement('div');
    el.className = 'pdv-toast pdv-toast--' + (type || 'info');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.textContent = msg;
    box.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-in'); });
    setTimeout(function () {
      el.classList.remove('is-in');
      setTimeout(function () { el.remove(); }, 250);
    }, type === 'error' ? 5000 : 2800);
  }

  /* ───────────────────────── modal ───────────────────────── */

  var modal = {
    el: $('pdv-modal'),
    onClose: null,
    lastFocus: null,
    locked: false,
    open: function (o) {
      this.lastFocus = document.activeElement;
      this.onClose = o.onClose || null;
      this.locked = false;
      $('pdv-modal-title').textContent = o.title || '';
      $('pdv-modal-body').innerHTML = o.body || '';
      $('pdv-modal-foot').innerHTML = o.foot || '';
      $('pdv-modal-foot').hidden = !o.foot;
      this.el.querySelector('.pdv-modal-dialog').className = 'pdv-modal-dialog' + (o.size ? ' pdv-modal-dialog--' + o.size : '');
      this.el.hidden = false;
      document.body.classList.add('pdv-modal-open');
      var first = this.el.querySelector('[autofocus]') || this.el.querySelector('.pdv-modal-dialog');
      setTimeout(function () { first.focus(); if (first.select) first.select(); }, 30);
      if (o.onOpen) o.onOpen($('pdv-modal-body'), $('pdv-modal-foot'));
    },
    close: function (force) {
      if (this.el.hidden || (this.locked && !force)) return;
      this.el.hidden = true;
      document.body.classList.remove('pdv-modal-open');
      var cb = this.onClose;
      this.onClose = null;
      if (cb) cb();
      if (this.lastFocus && this.lastFocus.focus) this.lastFocus.focus();
    },
    isOpen: function () { return !this.el.hidden; }
  };
  modal.el.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) modal.close();
  });
  // Tab preso dentro da janela.
  modal.el.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var f = modal.el.querySelectorAll('button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  function confirmDialog(title, text, okLabel, danger) {
    return new Promise(function (resolve) {
      var answered = false;
      modal.open({
        title: title,
        size: 'sm',
        body: '<p class="pdv-modal-text">' + esc(text) + '</p>',
        foot: '<button type="button" class="pdv-btn pdv-btn--ghost" data-close>Cancelar</button>' +
          '<button type="button" class="pdv-btn ' + (danger ? 'pdv-btn--danger' : 'pdv-btn--primary') + '" id="pdv-confirm-ok" autofocus>' + esc(okLabel || 'Confirmar') + '</button>',
        onOpen: function () {
          $('pdv-confirm-ok').addEventListener('click', function () { answered = true; modal.close(); resolve(true); });
        },
        onClose: function () { if (!answered) resolve(false); }
      });
    });
  }

  /* ───────────────────────── relógio ───────────────────────── */

  function tickClock() {
    var d = new Date();
    $('pdv-clock').textContent = d.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' }) +
      ' · ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  /* ───────────────────────── produtos ───────────────────────── */

  function coverUrl(p) {
    var imgs = p.images || [];
    var cover = imgs.filter(function (i) { return i.is_cover; })[0] || imgs[0];
    return cover && cover.url ? cover.url : '';
  }

  function hasVariants(p) {
    if (p.variants_matrix && p.variants_matrix.colors && p.variants_matrix.colors.length) return true;
    return simpleVariants(p).length > 0;
  }

  function simpleVariants(p) {
    if (p.variants_matrix) return [];
    return (p.variants || []).filter(function (v) { return v.variant_type && v.variant_type !== '_meta' && v.variant_type !== 'combinacao'; });
  }

  function productStock(p) {
    var n = parseInt(p.stock_quantity, 10);
    return isNaN(n) || n < 0 ? 0 : n;
  }

  /** Quanto deste produto/variação já está no carrinho. */
  function inCart(productId, variantKey) {
    return state.cart.reduce(function (s, l) {
      return s + (l.product_id === productId && (variantKey === undefined || l.variant_key === variantKey) ? l.qty : 0);
    }, 0);
  }

  function filteredProducts() {
    var q = norm(state.search.trim());
    var code = q.replace(/^#/, '');
    var isCode = /^\d+$/.test(code);
    return state.products.filter(function (p) {
      if (state.onlyStock && productStock(p) < 1) return false;
      if (state.category && (p.vitrine_category_ids || []).map(Number).indexOf(state.category) === -1) return false;
      if (!q) return true;
      if (isCode && String(p.id) === code) return true;
      return norm(p.display_name || p.name).indexOf(q) !== -1;
    });
  }

  function renderCategories() {
    var box = $('pdv-categories');
    var used = {};
    state.products.forEach(function (p) { (p.vitrine_category_ids || []).forEach(function (id) { used[Number(id)] = true; }); });
    var cats = state.categories.filter(function (c) { return used[Number(c.id)]; });
    if (!cats.length) { box.hidden = true; return; }
    box.hidden = false;
    box.innerHTML = '<button type="button" class="pdv-chip' + (state.category ? '' : ' is-active') + '" data-cat="0">Todos</button>' +
      cats.map(function (c) {
        return '<button type="button" class="pdv-chip' + (state.category === Number(c.id) ? ' is-active' : '') + '" data-cat="' + Number(c.id) + '">' +
          (c.icon_url ? '<span class="pdv-chip-icon"><img src="' + esc(c.icon_url) + '" alt="" width="16" height="16" referrerpolicy="no-referrer"></span>' : '') +
          esc(c.name) + '</button>';
      }).join('');
  }

  function renderProducts() {
    if (!state.loaded) return; // mantém o skeleton até a primeira carga
    var list = filteredProducts();
    var box = $('pdv-product-list');
    if (!list.length) {
      box.innerHTML = '<div class="pdv-products-empty">' +
        (state.products.length ? 'Nenhum produto encontrado' + (state.search ? ' para “' + esc(state.search) + '”' : '') + '.' : 'Nenhum produto cadastrado nesta loja.') +
        '</div>';
      return;
    }
    box.innerHTML = list.map(function (p) {
      var stock = productStock(p);
      var avail = stock - inCart(p.id);
      var min = parseInt(p.min_stock, 10) || 0;
      var out = stock < 1;
      var low = !out && min > 0 && stock <= min;
      var img = coverUrl(p);
      var variants = hasVariants(p);
      var name = p.display_name || p.name;
      var badge = out ? '<span class="pdv-badge pdv-badge--danger">Esgotado</span>'
        : low ? '<span class="pdv-badge pdv-badge--warning">' + (stock === 1 ? 'Última unidade' : 'Últimas ' + stock) + '</span>'
        : '';
      return '<button type="button" class="pdv-product' + (out ? ' is-out' : '') + '" data-id="' + p.id + '"' + (out ? ' disabled' : '') + ' title="' + esc(name) + '">' +
        '<span class="pdv-product-media">' +
          (img ? '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async">' : '<span class="pdv-product-initials">' + esc(initials(name)) + '</span>') +
          badge +
          (inCart(p.id) ? '<span class="pdv-product-incart">' + inCart(p.id) + '</span>' : '') +
        '</span>' +
        '<span class="pdv-product-info">' +
          '<span class="pdv-product-name">' + esc(name) + '</span>' +
          '<span class="pdv-product-meta">#' + p.id + (variants ? ' · Variações' : '') + '</span>' +
          '<span class="pdv-product-foot">' +
            '<span class="pdv-product-price">' + money(p.sale_price) + '</span>' +
            '<span class="pdv-product-stock' + (avail < 1 ? ' is-zero' : '') + '">' + Math.max(0, avail) + ' un.</span>' +
          '</span>' +
        '</span>' +
      '</button>';
    }).join('');
  }

  function loadProducts() {
    return api('/products').then(function (res) {
      state.products = res.products || [];
      state.loaded = true;
      // Reconcilia o carrinho salvo com o estoque/preço atuais.
      state.cart = state.cart.filter(function (l) {
        var p = findProduct(l.product_id);
        if (!p) return false;
        l.price = parseFloat(p.sale_price) || 0;
        l.name = p.display_name || p.name;
        l.image = coverUrl(p);
        l.stock_max = l.variant_key ? variantStock(p, l.variant_key) : productStock(p);
        if (l.qty > l.stock_max) l.qty = l.stock_max;
        return l.qty > 0;
      });
      renderCategories();
      renderProducts();
      renderCart();
    }).catch(function (e) {
      $('pdv-product-list').innerHTML = '<div class="pdv-products-empty">Não foi possível carregar os produtos. ' + esc(e.message) + '</div>';
    });
  }

  function loadCategories() {
    return api('/vitrine-categories').then(function (res) {
      state.categories = res.categories || [];
    }).catch(function () { state.categories = []; });
  }

  function findProduct(id) {
    id = Number(id);
    for (var i = 0; i < state.products.length; i++) if (Number(state.products[i].id) === id) return state.products[i];
    return null;
  }

  /* ───────────────────────── variações ───────────────────────── */

  function variantStock(p, key) {
    if (p.variants_matrix && key.indexOf('|') !== -1) {
      var parts = key.split('|');
      var row = (p.variants_matrix.stock || {})[parts[1]] || {};
      return Math.max(0, parseInt(row[parts[0]], 10) || 0);
    }
    var tv = key.split(':');
    var found = simpleVariants(p).filter(function (v) { return v.variant_type === tv[0] && v.variant_value === tv.slice(1).join(':'); })[0];
    return found ? Math.max(0, parseInt(found.stock_quantity, 10) || 0) : 0;
  }

  function variantLabel(p, key) {
    if (!key) return '';
    if (key.indexOf('|') !== -1 && key.indexOf(':') === -1) {
      var parts = key.split('|');
      var axis = (p && p.variants_matrix && p.variants_matrix.axis_label) || 'Tam.';
      return parts[0] + ' · ' + axis + ' ' + parts[1];
    }
    var v = simpleVariants(p || {}).filter(function (x) { return key === x.variant_type + ':' + x.variant_value; })[0];
    return v ? (v.variant_type_label + ': ' + v.variant_value) : key.replace(':', ': ');
  }

  function openVariantPicker(p) {
    var name = p.display_name || p.name;
    var m = p.variants_matrix;
    var body;
    if (m && m.colors && m.colors.length) {
      var meta = m.color_meta || {};
      body = '<div class="pdv-variant-head">' + productThumb(p, 56) +
        '<div><p class="pdv-variant-name">' + esc(name) + '</p><p class="pdv-muted">' + money(p.sale_price) + '</p></div></div>' +
        '<p class="pdv-field-label">Cor</p><div class="pdv-variant-colors" id="pdv-v-colors">' +
        m.colors.map(function (c, i) {
          var total = (m.sizes || []).reduce(function (s, sz) { return s + Math.max(0, parseInt(((m.stock || {})[sz] || {})[c], 10) || 0); }, 0);
          var hex = meta[c] && (meta[c].hex || meta[c]);
          return '<button type="button" class="pdv-swatch' + (total < 1 ? ' is-out' : '') + '" data-color="' + esc(c) + '"' + (total < 1 ? ' disabled' : '') + '>' +
            '<span class="pdv-swatch-dot"' + (typeof hex === 'string' ? ' style="background:' + esc(hex) + '"' : '') + '></span>' + esc(c) + '</button>';
        }).join('') + '</div>' +
        '<p class="pdv-field-label">' + esc(m.axis_label || 'Tamanho') + '</p><div class="pdv-variant-sizes" id="pdv-v-sizes"><p class="pdv-muted">Escolha a cor primeiro.</p></div>';
    } else {
      body = '<div class="pdv-variant-head">' + productThumb(p, 56) +
        '<div><p class="pdv-variant-name">' + esc(name) + '</p><p class="pdv-muted">' + money(p.sale_price) + '</p></div></div>' +
        '<div class="pdv-variant-sizes">' + simpleVariants(p).map(function (v) {
          var key = v.variant_type + ':' + v.variant_value;
          var avail = Math.max(0, (parseInt(v.stock_quantity, 10) || 0) - inCart(p.id, key));
          return '<button type="button" class="pdv-size" data-key="' + esc(key) + '"' + (avail < 1 ? ' disabled' : '') + '>' +
            '<strong>' + esc(v.variant_value) + '</strong><small>' + esc(v.variant_type_label) + ' · ' + avail + ' un.</small></button>';
        }).join('') + '</div>';
    }

    modal.open({
      title: 'Escolha a variação',
      body: body,
      onOpen: function (bodyEl) {
        bodyEl.addEventListener('click', function (e) {
          var sw = e.target.closest('.pdv-swatch');
          if (sw && !sw.disabled) {
            bodyEl.querySelectorAll('.pdv-swatch').forEach(function (b) { b.classList.toggle('is-active', b === sw); });
            var color = sw.getAttribute('data-color');
            $('pdv-v-sizes').innerHTML = (m.sizes || []).map(function (sz) {
              var key = color + '|' + sz;
              var avail = Math.max(0, variantStock(p, key) - inCart(p.id, key));
              return '<button type="button" class="pdv-size" data-key="' + esc(key) + '"' + (avail < 1 ? ' disabled' : '') + '>' +
                '<strong>' + esc(sz) + '</strong><small>' + avail + ' un.</small></button>';
            }).join('');
            var firstSize = $('pdv-v-sizes').querySelector('.pdv-size:not([disabled])');
            if (firstSize) firstSize.focus();
            return;
          }
          var size = e.target.closest('.pdv-size');
          if (size && !size.disabled) {
            modal.close();
            addToCart(p, size.getAttribute('data-key'));
          }
        });
      }
    });
  }

  function productThumb(p, size) {
    var img = coverUrl(p);
    var name = p.display_name || p.name;
    return '<span class="pdv-thumb" style="width:' + size + 'px;height:' + size + 'px">' +
      (img ? '<img src="' + esc(img) + '" alt="">' : '<span>' + esc(initials(name)) + '</span>') + '</span>';
  }

  /* ───────────────────────── carrinho ───────────────────────── */

  function saveCart() {
    try {
      localStorage.setItem(CART_KEY, JSON.stringify({ cart: state.cart, discount: state.discount, customer: state.customer }));
    } catch (e) { /* armazenamento indisponível: segue sem persistir */ }
  }

  function restoreCart() {
    try {
      var raw = localStorage.getItem(CART_KEY);
      if (!raw) return;
      var d = JSON.parse(raw);
      state.cart = Array.isArray(d.cart) ? d.cart : [];
      state.discount = d.discount && typeof d.discount.value === 'number' ? d.discount : { mode: 'value', value: 0 };
      state.customer = typeof d.customer === 'string' ? d.customer : '';
      $('pdv-customer-name').value = state.customer;
    } catch (e) { /* ignora carrinho corrompido */ }
  }

  function addToCart(p, variantKey) {
    if (!state.register) { toast('Abra o caixa antes de vender.', 'error'); return; }
    variantKey = variantKey || null;
    if (!variantKey && hasVariants(p)) { openVariantPicker(p); return; }
    var max = variantKey ? variantStock(p, variantKey) : productStock(p);
    var line = state.cart.filter(function (l) { return l.product_id === Number(p.id) && l.variant_key === variantKey; })[0];
    var current = line ? line.qty : 0;
    if (current + 1 > max) {
      toast(max < 1 ? 'Produto sem estoque.' : 'Estoque máximo atingido: ' + max + ' un.', 'error');
      return;
    }
    if (line) {
      line.qty++;
      state.cart.splice(state.cart.indexOf(line), 1);
      state.cart.unshift(line);
    } else {
      state.cart.unshift({
        product_id: Number(p.id),
        variant_key: variantKey,
        variant_label: variantKey ? variantLabel(p, variantKey) : '',
        name: p.display_name || p.name,
        price: parseFloat(p.sale_price) || 0,
        qty: 1,
        stock_max: max,
        image: coverUrl(p)
      });
    }
    renderCart(true);
    renderProducts();
  }

  function lineKey(l) { return l.product_id + '#' + (l.variant_key || ''); }

  function setQty(key, qty) {
    var line = state.cart.filter(function (l) { return lineKey(l) === key; })[0];
    if (!line) return;
    qty = parseInt(qty, 10);
    if (isNaN(qty) || qty < 1) {
      state.cart = state.cart.filter(function (l) { return l !== line; });
    } else if (qty > line.stock_max) {
      line.qty = line.stock_max;
      toast('Estoque disponível: ' + line.stock_max + ' un.', 'error');
    } else {
      line.qty = qty;
    }
    renderCart();
    renderProducts();
  }

  function subtotal() {
    return round2(state.cart.reduce(function (s, l) { return s + l.price * l.qty; }, 0));
  }

  function discountValue() {
    var sub = subtotal();
    var d = state.discount.mode === 'percent' ? sub * (state.discount.value / 100) : state.discount.value;
    return round2(Math.min(Math.max(0, d), sub));
  }

  function total() { return round2(subtotal() - discountValue()); }

  function itemCount() { return state.cart.reduce(function (s, l) { return s + l.qty; }, 0); }

  function renderCart(flashFirst) {
    var ul = $('pdv-cart-items');
    ul.innerHTML = state.cart.map(function (l, i) {
      var key = esc(lineKey(l));
      return '<li class="pdv-line' + (flashFirst && i === 0 ? ' is-flash' : '') + '" data-key="' + key + '">' +
        '<span class="pdv-thumb">' + (l.image ? '<img src="' + esc(l.image) + '" alt="">' : '<span>' + esc(initials(l.name)) + '</span>') + '</span>' +
        '<div class="pdv-line-info">' +
          '<span class="pdv-line-name">' + esc(l.name) + '</span>' +
          (l.variant_label ? '<span class="pdv-line-variant">' + esc(l.variant_label) + '</span>' : '') +
          '<span class="pdv-line-unit">' + money(l.price) + ' un.</span>' +
        '</div>' +
        '<div class="pdv-line-side">' +
          '<span class="pdv-line-total">' + money(l.price * l.qty) + '</span>' +
          '<div class="pdv-stepper">' +
            '<button type="button" data-step="-1" aria-label="Diminuir">' + icon(l.qty <= 1 ? ICON.trash : ICON.minus, 14) + '</button>' +
            '<input type="number" inputmode="numeric" min="1" max="' + l.stock_max + '" value="' + l.qty + '" aria-label="Quantidade">' +
            '<button type="button" data-step="1" aria-label="Aumentar"' + (l.qty >= l.stock_max ? ' disabled' : '') + '>' + icon(ICON.plus, 14) + '</button>' +
          '</div>' +
        '</div>' +
      '</li>';
    }).join('');

    var empty = state.cart.length === 0;
    $('pdv-cart-empty').hidden = !empty;
    var n = itemCount();
    $('pdv-cart-count').textContent = empty ? 'Nenhum item' : n + (n === 1 ? ' item' : ' itens');
    $('pdv-subtotal').textContent = money(subtotal());
    var d = discountValue();
    $('pdv-discount').textContent = '− ' + money(d);
    $('pdv-discount').classList.toggle('is-active', d > 0);
    $('pdv-discount-label').textContent = d > 0
      ? 'Desconto' + (state.discount.mode === 'percent' ? ' (' + String(state.discount.value).replace('.', ',') + '%)' : '')
      : 'Adicionar desconto';
    $('pdv-total').textContent = money(total());
    $('pdv-clear').disabled = empty;
    $('pdv-discount-btn').disabled = empty || !state.register;
    $('pdv-finish').disabled = empty || !state.register;
    saveCart();
  }

  $('pdv-cart-items').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-step]');
    if (!btn) return;
    var li = btn.closest('.pdv-line');
    var line = state.cart.filter(function (l) { return lineKey(l) === li.getAttribute('data-key'); })[0];
    if (line) setQty(lineKey(line), line.qty + parseInt(btn.getAttribute('data-step'), 10));
  });
  $('pdv-cart-items').addEventListener('change', function (e) {
    if (e.target.matches('input[type="number"]')) setQty(e.target.closest('.pdv-line').getAttribute('data-key'), e.target.value);
  });

  function resetSale() {
    state.cart = [];
    state.discount = { mode: 'value', value: 0 };
    state.customer = '';
    $('pdv-customer-name').value = '';
    renderCart();
    renderProducts();
  }

  /* ───────────────────────── desconto ───────────────────────── */

  function openDiscount() {
    if (!state.cart.length || !state.register) return;
    var sub = subtotal();
    var mode = state.discount.mode;
    modal.open({
      title: 'Desconto na venda',
      size: 'sm',
      body:
        '<div class="pdv-segment" role="radiogroup" aria-label="Tipo de desconto">' +
          '<button type="button" role="radio" data-mode="value">Valor (R$)</button>' +
          '<button type="button" role="radio" data-mode="percent">Percentual (%)</button>' +
        '</div>' +
        '<label class="pdv-field"><span class="pdv-field-label">Desconto</span>' +
          '<input type="text" inputmode="decimal" class="pdv-input pdv-input--lg" id="pdv-disc-input" autofocus value="' +
          (state.discount.value ? String(state.discount.value).replace('.', ',') : '') + '" placeholder="0,00"></label>' +
        '<div class="pdv-quick" id="pdv-disc-quick"></div>' +
        '<dl class="pdv-breakdown">' +
          '<div><dt>Subtotal</dt><dd>' + money(sub) + '</dd></div>' +
          '<div><dt>Desconto</dt><dd id="pdv-disc-preview">− R$ 0,00</dd></div>' +
          '<div class="is-total"><dt>Total</dt><dd id="pdv-disc-total">' + money(sub) + '</dd></div>' +
        '</dl>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" id="pdv-disc-remove">Remover desconto</button>' +
        '<button type="button" class="pdv-btn pdv-btn--primary" id="pdv-disc-apply">Aplicar</button>',
      onOpen: function (body) {
        var input = $('pdv-disc-input');
        function calc() {
          var v = parseMoney(input.value);
          var d = mode === 'percent' ? sub * Math.min(v, 100) / 100 : Math.min(v, sub);
          d = round2(Math.max(0, d));
          $('pdv-disc-preview').textContent = '− ' + money(d);
          $('pdv-disc-total').textContent = money(sub - d);
          return v;
        }
        function setMode(m) {
          mode = m;
          body.querySelectorAll('[data-mode]').forEach(function (b) {
            var on = b.getAttribute('data-mode') === m;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
          });
          $('pdv-disc-quick').innerHTML = (m === 'percent' ? [5, 10, 15, 20] : [5, 10, 20, 50]).map(function (q) {
            return '<button type="button" class="pdv-chip" data-q="' + q + '">' + (m === 'percent' ? q + '%' : money(q)) + '</button>';
          }).join('');
          calc();
        }
        body.addEventListener('click', function (e) {
          var mb = e.target.closest('[data-mode]');
          if (mb) { setMode(mb.getAttribute('data-mode')); input.focus(); return; }
          var qb = e.target.closest('[data-q]');
          if (qb) { input.value = qb.getAttribute('data-q'); calc(); input.focus(); }
        });
        input.addEventListener('input', calc);
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') $('pdv-disc-apply').click(); });
        setMode(mode);
        $('pdv-disc-apply').addEventListener('click', function () {
          var v = calc();
          if (mode === 'percent' && v > 100) { toast('O percentual máximo é 100%.', 'error'); return; }
          if (mode === 'value' && v > sub) { toast('O desconto não pode passar do subtotal.', 'error'); return; }
          state.discount = { mode: mode, value: v };
          modal.close();
          renderCart();
        });
        $('pdv-disc-remove').addEventListener('click', function () {
          state.discount = { mode: 'value', value: 0 };
          modal.close();
          renderCart();
        });
      }
    });
  }

  /* ───────────────────────── checkout ───────────────────────── */

  /**
   * Um pedido por tentativa de checkout. Se o operador gera o PIX e depois
   * troca para dinheiro, reaproveita o mesmo pedido — o servidor cancela o
   * pagamento anterior em vez de criar um segundo pedido pendente.
   */
  var checkout = { order: null };

  function ensureOrder() {
    if (checkout.order) return Promise.resolve(checkout.order);
    return api('/orders', {
      method: 'POST',
      body: {
        order_type: 'pdv',
        customer_name: state.customer,
        discount: discountValue(),
        items: state.cart.map(function (l) {
          var it = { product_id: l.product_id, quantity: l.qty };
          if (l.variant_key) it.variant_key = l.variant_key;
          return it;
        })
      }
    }).then(function (res) {
      checkout.order = res.order;
      return res.order;
    });
  }

  function payAndConfirm(method) {
    return ensureOrder().then(function (order) {
      return api('/payments', { method: 'POST', body: { order_id: order.id, method: method } }).then(function (res) {
        return { order: order, payment: res.payment };
      });
    });
  }

  function confirmPayment(paymentId) {
    return api('/payments/confirm', { method: 'POST', body: { payment_id: paymentId } });
  }

  function openCheckout() {
    if (!state.cart.length) return;
    if (!state.register) { toast('Abra o caixa antes de finalizar.', 'error'); return; }
    checkout.order = null;
    var amount = total();
    var method = 'dinheiro';
    var pixPayment = null;

    var methods = ['dinheiro', 'cartao', 'pix'].map(function (m, i) {
      var ic = m === 'dinheiro' ? ICON.cash : m === 'cartao' ? ICON.card : ICON.pix;
      return '<button type="button" class="pdv-method" role="radio" data-method="' + m + '">' + icon(ic, 22) +
        '<span>' + METHOD_LABEL[m] + '</span><kbd class="pdv-kbd">' + (i + 1) + '</kbd></button>';
    }).join('');

    modal.open({
      title: 'Pagamento',
      size: 'md',
      body:
        '<div class="pdv-pay-total"><span>Total a receber</span><strong>' + money(amount) + '</strong>' +
          '<small>' + itemCount() + (itemCount() === 1 ? ' item' : ' itens') +
          (discountValue() > 0 ? ' · desconto de ' + money(discountValue()) : '') + '</small></div>' +
        '<div class="pdv-methods" role="radiogroup" aria-label="Forma de pagamento">' + methods + '</div>' +
        '<div class="pdv-pay-panel" id="pdv-pay-panel"></div>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" data-close>Voltar</button>' +
        '<button type="button" class="pdv-btn pdv-btn--success pdv-btn--lg" id="pdv-pay-confirm">' + icon(ICON.check, 18) + ' <span>Confirmar pagamento</span></button>',
      onOpen: function (body) {
        var btn = $('pdv-pay-confirm');

        function setBusy(on, label) {
          state.busy = on;
          modal.locked = on;
          btn.disabled = on;
          body.querySelectorAll('.pdv-method').forEach(function (b) { b.disabled = on; });
          if (label) btn.querySelector('span').textContent = label;
        }

        function renderPanel() {
          body.querySelectorAll('.pdv-method').forEach(function (b) {
            var on = b.getAttribute('data-method') === method;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
          });
          var panel = $('pdv-pay-panel');
          btn.querySelector('span').textContent = 'Confirmar pagamento';
          btn.disabled = false;

          if (method === 'dinheiro') {
            var quick = [amount];
            [5, 10, 20, 50, 100, 200].forEach(function (note) {
              var v = Math.ceil(amount / note) * note;
              if (v > amount && quick.indexOf(v) === -1 && quick.length < 5) quick.push(v);
            });
            panel.innerHTML =
              '<label class="pdv-field"><span class="pdv-field-label">Valor recebido</span>' +
                '<input type="text" inputmode="decimal" class="pdv-input pdv-input--xl" id="pdv-received" placeholder="0,00" autocomplete="off"></label>' +
              '<div class="pdv-quick">' + quick.map(function (v, i) {
                return '<button type="button" class="pdv-chip" data-recv="' + v + '">' + (i === 0 ? 'Valor exato' : money(v)) + '</button>';
              }).join('') + '</div>' +
              '<div class="pdv-change" id="pdv-change"><span>Troco</span><strong>R$ 0,00</strong></div>';
            var input = $('pdv-received');
            var upd = function () {
              var recv = input.value.trim() === '' ? amount : parseMoney(input.value);
              var change = round2(recv - amount);
              var box = $('pdv-change');
              box.classList.toggle('is-short', change < 0);
              box.querySelector('span').textContent = change < 0 ? 'Falta' : 'Troco';
              box.querySelector('strong').textContent = money(Math.abs(change));
              btn.disabled = change < 0;
            };
            input.addEventListener('input', upd);
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !btn.disabled) { e.preventDefault(); btn.click(); } });
            panel.querySelector('.pdv-quick').addEventListener('click', function (e) {
              var q = e.target.closest('[data-recv]');
              if (q) { input.value = String(q.getAttribute('data-recv')).replace('.', ','); upd(); input.focus(); }
            });
            upd();
            setTimeout(function () { input.focus(); }, 20);
          } else if (method === 'cartao') {
            panel.innerHTML = '<div class="pdv-pay-hint">' + icon(ICON.card, 28) +
              '<div><strong>Passe o cartão na maquininha</strong><p>Cobre ' + money(amount) + ' em débito ou crédito e confirme somente depois da aprovação.</p></div></div>';
            btn.querySelector('span').textContent = 'Pagamento aprovado';
            btn.focus();
          } else {
            if (pixPayment) { renderPix(); return; }
            panel.innerHTML = '<div class="pdv-pay-hint">' + icon(ICON.pix, 28) +
              '<div><strong>Cobrança via PIX</strong><p>Gere o QR Code para o cliente escanear. Confirme quando o valor cair na conta.</p></div></div>';
            btn.querySelector('span').textContent = 'Gerar QR Code';
            btn.focus();
          }
        }

        function renderPix() {
          var panel = $('pdv-pay-panel');
          var qr = pixPayment.pix_qr_code;
          var manual = pixPayment.pix_manual;
          var src = qr ? (/^(data:|https?:)/.test(qr) ? qr : 'data:image/png;base64,' + qr) : '';
          panel.innerHTML = '<div class="pdv-pix">' +
            (src ? '<img class="pdv-pix-qr" src="' + esc(src) + '" alt="QR Code PIX de ' + esc(money(amount)) + '">'
                 : '<div class="pdv-pix-missing">' + icon(ICON.pix, 32) + '<p>QR Code indisponível. Verifique a chave PIX em Configurações.</p></div>') +
            (manual && manual.copia_cola
              ? '<div class="pdv-pix-copy"><input class="pdv-input" readonly value="' + esc(manual.copia_cola) + '" aria-label="PIX copia e cola">' +
                '<button type="button" class="pdv-btn pdv-btn--ghost" id="pdv-pix-copy">' + icon(ICON.copy, 16) + ' Copiar</button></div>' +
                '<p class="pdv-muted">Recebedor: ' + esc(manual.nome || '') + ' · Chave: ' + esc(manual.chave || '') + '</p>'
              : '') +
            '</div>';
          var cp = $('pdv-pix-copy');
          if (cp) cp.addEventListener('click', function () {
            var txt = manual.copia_cola;
            (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () {
              toast('Código PIX copiado.', 'success');
            }).catch(function () {
              panel.querySelector('.pdv-pix-copy input').select();
            });
          });
          btn.querySelector('span').textContent = 'PIX recebido';
          btn.disabled = false;
        }

        body.addEventListener('click', function (e) {
          var mb = e.target.closest('.pdv-method');
          if (mb && !state.busy) { method = mb.getAttribute('data-method'); renderPanel(); }
        });
        modal.el.addEventListener('keydown', keyMethod);
        function keyMethod(e) {
          if (state.busy || e.target.tagName === 'INPUT') return;
          var map = { '1': 'dinheiro', '2': 'cartao', '3': 'pix' };
          if (map[e.key]) { e.preventDefault(); method = map[e.key]; renderPanel(); }
        }
        modal.onClose = function () { modal.el.removeEventListener('keydown', keyMethod); };

        btn.addEventListener('click', function () {
          if (state.busy) return;

          // PIX em duas etapas: gerar a cobrança, depois confirmar o recebimento.
          if (method === 'pix' && !pixPayment) {
            setBusy(true, 'Gerando QR Code…');
            payAndConfirm('pix').then(function (r) {
              pixPayment = r.payment;
              setBusy(false);
              renderPix();
            }).catch(function (e) {
              setBusy(false);
              renderPanel();
              toast(e.message, 'error');
            });
            return;
          }

          var received = null;
          if (method === 'dinheiro') {
            var v = $('pdv-received').value.trim();
            received = v === '' ? amount : parseMoney(v);
            if (received < amount) { toast('Valor recebido menor que o total.', 'error'); return; }
          }

          setBusy(true, 'Registrando venda…');
          var step = method === 'pix'
            ? Promise.resolve({ order: checkout.order, payment: pixPayment })
            : payAndConfirm(method);
          step.then(function (r) {
            return confirmPayment(r.payment.id).then(function () { return r; });
          }).then(function (r) {
            setBusy(false);
            var sale = buildSale(r.order, method, received);
            modal.close(true);
            finishSale(sale);
          }).catch(function (e) {
            setBusy(false);
            if (method === 'pix') renderPix(); else renderPanel();
            toast(e.message, 'error');
            // Estoque mudou entre a tela e o servidor: atualiza o catálogo.
            if (/estoque/i.test(e.message)) loadProducts();
          });
        });

        renderPanel();
      }
    });
  }

  function buildSale(order, method, received) {
    return {
      id: order.id,
      date: new Date(),
      items: state.cart.map(function (l) { return { name: l.name, variant: l.variant_label, qty: l.qty, price: l.price }; }),
      subtotal: subtotal(),
      discount: discountValue(),
      total: total(),
      method: method,
      received: received,
      change: received != null ? round2(received - total()) : 0,
      customer: state.customer,
      operator: CFG.userName
    };
  }

  function finishSale(sale) {
    resetSale();
    try { localStorage.removeItem(CART_KEY); } catch (e) { /* nada */ }
    loadProducts();
    loadSummary();

    modal.open({
      title: 'Venda concluída',
      size: 'sm',
      body: '<div class="pdv-success">' +
          '<span class="pdv-success-icon">' + icon(ICON.check, 34) + '</span>' +
          '<p class="pdv-success-order">Pedido #' + sale.id + ' · ' + METHOD_LABEL[sale.method] + '</p>' +
          '<p class="pdv-success-total">' + money(sale.total) + '</p>' +
          (sale.method === 'dinheiro' && sale.change > 0
            ? '<div class="pdv-success-change"><span>Troco</span><strong>' + money(sale.change) + '</strong></div>'
            : '') +
        '</div>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" id="pdv-print">' + icon(ICON.print, 16) + ' Imprimir cupom</button>' +
        '<button type="button" class="pdv-btn pdv-btn--primary" id="pdv-new-sale" autofocus>Nova venda <kbd class="pdv-kbd pdv-kbd--on-primary">Enter</kbd></button>',
      onOpen: function () {
        $('pdv-print').addEventListener('click', function () { printReceipt(sale); });
        $('pdv-new-sale').addEventListener('click', function () { modal.close(); });
      },
      onClose: function () { setTimeout(function () { $('pdv-search').focus(); }, 40); }
    });
  }

  /* ───────────────────────── cupom ───────────────────────── */

  function printReceipt(sale) {
    var d = sale.date instanceof Date ? sale.date : new Date(sale.date);
    var rows = sale.items.map(function (it) {
      return '<tr><td colspan="3" class="r-name">' + esc(it.name) + (it.variant ? ' <small>(' + esc(it.variant) + ')</small>' : '') + '</td></tr>' +
        '<tr><td>' + it.qty + ' x ' + money(it.price) + '</td><td></td><td class="r-right">' + money(it.qty * it.price) + '</td></tr>';
    }).join('');
    $('pdv-receipt-print').innerHTML =
      '<div class="r">' +
        '<p class="r-center r-store">' + esc(CFG.storeName) + '</p>' +
        '<p class="r-center">CUPOM NÃO FISCAL</p>' +
        '<p class="r-center">Pedido #' + sale.id + ' · ' + d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + '</p>' +
        '<hr><table>' + rows + '</table><hr>' +
        '<table>' +
          '<tr><td>Subtotal</td><td class="r-right">' + money(sale.subtotal) + '</td></tr>' +
          (sale.discount > 0 ? '<tr><td>Desconto</td><td class="r-right">− ' + money(sale.discount) + '</td></tr>' : '') +
          '<tr class="r-total"><td>TOTAL</td><td class="r-right">' + money(sale.total) + '</td></tr>' +
          '<tr><td>' + esc(METHOD_LABEL[sale.method] || sale.method) + '</td><td class="r-right">' + money(sale.received != null ? sale.received : sale.total) + '</td></tr>' +
          (sale.change > 0 ? '<tr><td>Troco</td><td class="r-right">' + money(sale.change) + '</td></tr>' : '') +
        '</table><hr>' +
        (sale.customer ? '<p>Cliente: ' + esc(sale.customer) + '</p>' : '') +
        (sale.operator ? '<p>Operador: ' + esc(sale.operator) + '</p>' : '') +
        '<p class="r-center">Obrigado pela preferência!</p>' +
      '</div>';
    document.body.classList.add('pdv-printing');
    setTimeout(function () {
      window.print();
      document.body.classList.remove('pdv-printing');
    }, 50);
  }

  function reprint(orderId) {
    api('/orders/' + orderId).then(function (o) {
      var paid = (o.payments || []).filter(function (p) { return p.status === 'confirmado'; })[0] || {};
      var items = (o.items || []).map(function (it) {
        return { name: it.product_name, variant: it.variant_key ? variantLabel(findProduct(it.product_id), it.variant_key) : '', qty: parseInt(it.quantity, 10), price: parseFloat(it.price) };
      });
      var sub = items.reduce(function (s, it) { return s + it.qty * it.price; }, 0);
      printReceipt({
        id: o.id,
        date: String(o.created_at).replace(' ', 'T'),
        items: items,
        subtotal: sub,
        discount: parseFloat(o.discount) || 0,
        total: parseFloat(o.total) || 0,
        method: paid.method || 'dinheiro',
        received: null,
        change: 0,
        customer: o.pdv_customer_name || '',
        operator: ''
      });
    }).catch(function (e) { toast(e.message, 'error'); });
  }

  /* ───────────────────────── caixa ───────────────────────── */

  function loadSummary() {
    return api('/cash/summary').then(function (res) {
      state.register = res.open ? res : null;
      renderRegister();
      renderCart();
      renderProducts();
    }).catch(function (e) {
      $('pdv-register').innerHTML = '<span class="pdv-register-pill pdv-register-pill--closed">Erro ao consultar o caixa</span>';
      toast(e.message, 'error');
    });
  }

  function timeOf(ts) {
    var d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d) ? '' : d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function renderRegister() {
    var r = state.register;
    app.setAttribute('data-state', r ? 'open' : 'closed');
    $('pdv-locked').hidden = !!r;
    var box = $('pdv-register');
    if (!r) {
      box.innerHTML = '<span class="pdv-register-pill pdv-register-pill--closed"><span class="pdv-dot"></span>Caixa fechado</span>' +
        '<button type="button" class="pdv-btn pdv-btn--primary" data-action="open-cash">' + icon(ICON.plus, 16) + ' Abrir caixa</button>';
      ['kpi-count', 'kpi-total', 'kpi-average', 'kpi-cash'].forEach(function (id) { $(id).textContent = '—'; });
      $('pdv-recent').innerHTML = '<p class="pdv-muted">Abra o caixa para começar o turno.</p>';
      return;
    }
    box.innerHTML =
      '<span class="pdv-register-pill pdv-register-pill--open"><span class="pdv-dot"></span>Caixa #' + r.open.id + ' aberto às ' + timeOf(r.open.opened_at) +
        (r.opened_by_name ? ' · ' + esc(r.opened_by_name) : '') + '</span>' +
      '<div class="pdv-register-actions">' +
        '<button type="button" class="pdv-btn pdv-btn--ghost" data-action="supply" title="Entrada de dinheiro na gaveta">' + icon(ICON.in, 16) + ' Suprimento</button>' +
        '<button type="button" class="pdv-btn pdv-btn--ghost" data-action="withdraw" title="Retirada de dinheiro da gaveta">' + icon(ICON.out, 16) + ' Sangria</button>' +
        '<button type="button" class="pdv-btn pdv-btn--ghost" data-action="movements">' + icon(ICON.list, 16) + ' Movimentos</button>' +
        '<button type="button" class="pdv-btn pdv-btn--danger-ghost" data-action="close-cash">' + icon(ICON.lock, 16) + ' Fechar caixa</button>' +
      '</div>';

    $('kpi-count').textContent = r.sales.count;
    $('kpi-total').textContent = money(r.sales.total);
    $('kpi-average').textContent = money(r.sales.average);
    $('kpi-cash').textContent = money(r.cash.expected);

    var recent = r.recent || [];
    $('pdv-recent').innerHTML = !recent.length
      ? '<p class="pdv-muted">Nenhuma venda registrada neste turno.</p>'
      : '<div class="pdv-table-wrap"><table class="pdv-table"><thead><tr><th>Pedido</th><th>Hora</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th class="num">Total</th><th></th></tr></thead><tbody>' +
        recent.map(function (s) {
          return '<tr><td>#' + s.id + '</td><td>' + timeOf(s.created_at) + '</td><td>' + esc(s.pdv_customer_name || '—') + '</td><td>' + (parseInt(s.items, 10) || 0) + '</td>' +
            '<td><span class="pdv-method-tag pdv-method-tag--' + esc(s.method) + '">' + esc(METHOD_LABEL[s.method] || s.method) + '</span></td>' +
            '<td class="num"><strong>' + money(s.total) + '</strong>' + (parseFloat(s.discount) > 0 ? '<small> (−' + money(s.discount) + ')</small>' : '') + '</td>' +
            '<td class="num"><button type="button" class="pdv-icon-btn" data-reprint="' + s.id + '" title="Reimprimir cupom" aria-label="Reimprimir cupom do pedido ' + s.id + '">' + icon(ICON.print, 16) + '</button></td></tr>';
        }).join('') + '</tbody></table></div>';
  }

  function openCashDialog() {
    modal.open({
      title: 'Abrir caixa',
      size: 'sm',
      body: '<p class="pdv-modal-text">Informe o fundo de troco: o dinheiro que está na gaveta agora, antes da primeira venda.</p>' +
        '<label class="pdv-field"><span class="pdv-field-label">Fundo de troco</span>' +
        '<input type="text" inputmode="decimal" class="pdv-input pdv-input--xl" id="pdv-open-amount" placeholder="0,00" autofocus></label>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" data-close>Cancelar</button>' +
        '<button type="button" class="pdv-btn pdv-btn--primary" id="pdv-open-ok">Abrir caixa</button>',
      onOpen: function () {
        var ok = $('pdv-open-ok');
        $('pdv-open-amount').addEventListener('keydown', function (e) { if (e.key === 'Enter') ok.click(); });
        ok.addEventListener('click', function () {
          ok.disabled = true;
          api('/cash/open', { method: 'POST', body: { initial_amount: parseMoney($('pdv-open-amount').value) } }).then(function () {
            modal.close(true);
            toast('Caixa aberto. Boas vendas!', 'success');
            return loadSummary();
          }).then(function () { $('pdv-search').focus(); })
            .catch(function (e) { ok.disabled = false; toast(e.message, 'error'); });
        });
      }
    });
  }

  function movementDialog(type) {
    var r = state.register;
    if (!r) return;
    var isOut = type === 'saida';
    modal.open({
      title: isOut ? 'Sangria' : 'Suprimento',
      size: 'sm',
      body: '<p class="pdv-modal-text">' + (isOut
          ? 'Retirada de dinheiro da gaveta (ex.: depósito, pagamento de fornecedor). Disponível: <strong>' + money(r.cash.expected) + '</strong>.'
          : 'Entrada de dinheiro na gaveta que não é venda (ex.: reforço de troco).') + '</p>' +
        '<label class="pdv-field"><span class="pdv-field-label">Valor</span>' +
        '<input type="text" inputmode="decimal" class="pdv-input pdv-input--xl" id="pdv-mov-amount" placeholder="0,00" autofocus></label>' +
        '<label class="pdv-field"><span class="pdv-field-label">Motivo</span>' +
        '<input type="text" class="pdv-input" id="pdv-mov-desc" maxlength="255" placeholder="' + (isOut ? 'Ex.: depósito bancário' : 'Ex.: reforço de troco') + '"></label>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" data-close>Cancelar</button>' +
        '<button type="button" class="pdv-btn ' + (isOut ? 'pdv-btn--danger' : 'pdv-btn--primary') + '" id="pdv-mov-ok">Registrar ' + (isOut ? 'sangria' : 'suprimento') + '</button>',
      onOpen: function () {
        var ok = $('pdv-mov-ok');
        ['pdv-mov-amount', 'pdv-mov-desc'].forEach(function (id) {
          $(id).addEventListener('keydown', function (e) { if (e.key === 'Enter') ok.click(); });
        });
        ok.addEventListener('click', function () {
          var amount = parseMoney($('pdv-mov-amount').value);
          if (amount <= 0) { toast('Informe um valor maior que zero.', 'error'); return; }
          ok.disabled = true;
          api('/cash/movements', {
            method: 'POST',
            body: { cash_register_id: r.open.id, type: type, amount: amount, description: $('pdv-mov-desc').value.trim() || (isOut ? 'Sangria' : 'Suprimento') }
          }).then(function () {
            modal.close(true);
            toast((isOut ? 'Sangria' : 'Suprimento') + ' de ' + money(amount) + ' registrado.', 'success');
            loadSummary();
          }).catch(function (e) { ok.disabled = false; toast(e.message, 'error'); });
        });
      }
    });
  }

  function movementsDialog() {
    var r = state.register;
    if (!r) return;
    var rows = [{ created_at: r.open.opened_at, type: 'entrada', amount: r.cash.initial, description: 'Abertura (fundo de troco)' }].concat(r.movements || []);
    modal.open({
      title: 'Movimentos do caixa #' + r.open.id,
      size: 'md',
      body: '<div class="pdv-table-wrap"><table class="pdv-table"><thead><tr><th>Hora</th><th>Descrição</th><th class="num">Valor</th></tr></thead><tbody>' +
        rows.map(function (m) {
          var out = m.type === 'saida';
          return '<tr><td>' + timeOf(m.created_at) + '</td><td>' + esc(m.description || (m.order_id ? 'Venda #' + m.order_id : '—')) + '</td>' +
            '<td class="num ' + (out ? 'is-neg' : 'is-pos') + '">' + (out ? '− ' : '+ ') + money(m.amount) + '</td></tr>';
        }).join('') +
        '</tbody><tfoot><tr><td colspan="2">Saldo em dinheiro</td><td class="num">' + money(r.cash.expected) + '</td></tr></tfoot></table></div>' +
        '<p class="pdv-muted">Vendas em cartão e PIX não passam pela gaveta e não aparecem aqui.</p>'
    });
  }

  function closeCashDialog() {
    var r = state.register;
    if (!r) return;
    if (state.cart.length) { toast('Finalize ou limpe a venda atual antes de fechar o caixa.', 'error'); return; }
    var byMethod = {};
    (r.sales.by_method || []).forEach(function (m) { byMethod[m.method] = m; });
    var methodRows = ['dinheiro', 'cartao', 'pix'].map(function (m) {
      var x = byMethod[m] || { count: 0, total: 0 };
      return '<div><dt>' + METHOD_LABEL[m] + ' <small>(' + x.count + ')</small></dt><dd>' + money(x.total) + '</dd></div>';
    }).join('');

    modal.open({
      title: 'Fechar caixa #' + r.open.id,
      size: 'md',
      body:
        '<div class="pdv-close-grid">' +
          '<section><h3 class="pdv-section-title">Vendas do turno</h3><dl class="pdv-breakdown">' + methodRows +
            (r.sales.discount > 0 ? '<div><dt>Descontos concedidos</dt><dd>− ' + money(r.sales.discount) + '</dd></div>' : '') +
            '<div class="is-total"><dt>Total vendido</dt><dd>' + money(r.sales.total) + '</dd></div></dl></section>' +
          '<section><h3 class="pdv-section-title">Gaveta (dinheiro)</h3><dl class="pdv-breakdown">' +
            '<div><dt>Fundo de troco</dt><dd>' + money(r.cash.initial) + '</dd></div>' +
            '<div><dt>Vendas em dinheiro</dt><dd>+ ' + money(r.cash.sales) + '</dd></div>' +
            '<div><dt>Suprimentos</dt><dd>+ ' + money(r.cash.supplies) + '</dd></div>' +
            '<div><dt>Sangrias</dt><dd>− ' + money(r.cash.withdrawals) + '</dd></div>' +
            '<div class="is-total"><dt>Esperado na gaveta</dt><dd>' + money(r.cash.expected) + '</dd></div></dl></section>' +
        '</div>' +
        '<label class="pdv-field"><span class="pdv-field-label">Valor contado na gaveta</span>' +
          '<input type="text" inputmode="decimal" class="pdv-input pdv-input--xl" id="pdv-close-amount" placeholder="0,00" autofocus></label>' +
        '<div class="pdv-diff" id="pdv-close-diff" hidden></div>',
      foot: '<button type="button" class="pdv-btn pdv-btn--ghost" data-close>Cancelar</button>' +
        '<button type="button" class="pdv-btn pdv-btn--danger" id="pdv-close-ok" disabled>Fechar caixa</button>',
      onOpen: function () {
        var input = $('pdv-close-amount');
        var ok = $('pdv-close-ok');
        input.addEventListener('input', function () {
          var has = input.value.trim() !== '';
          ok.disabled = !has;
          var diffEl = $('pdv-close-diff');
          diffEl.hidden = !has;
          if (!has) return;
          var diff = round2(parseMoney(input.value) - r.cash.expected);
          diffEl.className = 'pdv-diff ' + (diff === 0 ? 'is-ok' : diff > 0 ? 'is-over' : 'is-short');
          diffEl.innerHTML = diff === 0 ? icon(ICON.check, 16) + ' Caixa conferido, sem diferença.'
            : (diff > 0 ? 'Sobra de ' : 'Falta de ') + '<strong>' + money(Math.abs(diff)) + '</strong> em relação ao esperado.';
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !ok.disabled) ok.click(); });
        ok.addEventListener('click', function () {
          ok.disabled = true;
          var counted = parseMoney(input.value);
          api('/cash/close', { method: 'POST', body: { cash_register_id: r.open.id, final_amount: counted } }).then(function () {
            modal.close(true);
            var diff = round2(counted - r.cash.expected);
            toast('Caixa fechado.' + (diff ? ' Diferença: ' + money(diff) + '.' : ''), diff ? 'info' : 'success');
            return loadSummary();
          }).catch(function (e) { ok.disabled = false; toast(e.message, 'error'); });
        });
      }
    });
  }

  /* ───────────────────────── eventos ───────────────────────── */

  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-action]');
    if (a) {
      var act = a.getAttribute('data-action');
      if (act === 'open-cash') openCashDialog();
      else if (act === 'supply') movementDialog('entrada');
      else if (act === 'withdraw') movementDialog('saida');
      else if (act === 'movements') movementsDialog();
      else if (act === 'close-cash') closeCashDialog();
      return;
    }
    var rp = e.target.closest('[data-reprint]');
    if (rp) reprint(parseInt(rp.getAttribute('data-reprint'), 10));
  });

  $('pdv-product-list').addEventListener('click', function (e) {
    var b = e.target.closest('.pdv-product');
    if (!b || b.disabled) return;
    var p = findProduct(b.getAttribute('data-id'));
    if (p) addToCart(p);
  });

  $('pdv-categories').addEventListener('click', function (e) {
    var c = e.target.closest('[data-cat]');
    if (!c) return;
    state.category = parseInt(c.getAttribute('data-cat'), 10) || 0;
    renderCategories();
    renderProducts();
  });

  var searchInput = $('pdv-search');
  searchInput.addEventListener('input', function () { state.search = this.value; renderProducts(); });
  // Enter na busca: código exato ou resultado único vai direto para a venda.
  // É o fluxo de quem usa leitor de código de barras (que digita e tecla Enter).
  searchInput.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var q = this.value.trim().replace(/^#/, '');
    var list = filteredProducts().filter(function (p) { return productStock(p) > 0; });
    var exact = /^\d+$/.test(q) ? findProduct(q) : null;
    var pick = exact || (list.length === 1 ? list[0] : null);
    if (!pick) {
      toast(list.length ? 'Refine a busca: ' + list.length + ' produtos encontrados.' : 'Nenhum produto encontrado.', 'info');
      return;
    }
    addToCart(pick);
    if (!hasVariants(pick)) {
      this.value = '';
      state.search = '';
      renderProducts();
    }
  });

  $('pdv-only-stock').addEventListener('change', function () { state.onlyStock = this.checked; renderProducts(); });
  $('pdv-customer-name').addEventListener('input', function () { state.customer = this.value; saveCart(); });
  $('pdv-discount-btn').addEventListener('click', openDiscount);
  $('pdv-finish').addEventListener('click', openCheckout);
  $('pdv-clear').addEventListener('click', function () {
    confirmDialog('Limpar venda', 'Remover todos os ' + itemCount() + ' itens da venda atual?', 'Limpar venda', true).then(function (ok) {
      if (ok) { resetSale(); toast('Venda limpa.', 'info'); }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.isOpen()) { e.preventDefault(); modal.close(); return; }
    if (modal.isOpen()) {
      // Enter na janela de sucesso inicia a próxima venda.
      if (e.key === 'Enter' && $('pdv-new-sale') && document.activeElement === $('pdv-new-sale')) return;
      return;
    }
    if (e.key === 'F2') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
    else if (e.key === 'F4') { e.preventDefault(); openDiscount(); }
    else if (e.key === 'F9') { e.preventDefault(); openCheckout(); }
  });

  /* ───────────────────────── início ───────────────────────── */

  tickClock();
  setInterval(tickClock, 30000);
  restoreCart();
  Promise.all([loadCategories(), loadSummary()]).then(loadProducts).then(function () {
    if (state.register) searchInput.focus();
  });
})();
