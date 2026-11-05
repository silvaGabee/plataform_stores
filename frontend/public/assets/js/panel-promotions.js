/** Promoções e pagamentos (gerente): promo por produto, cupons, parcelamento, PIX, frete, descontos do PDV. */
(function () {
  'use strict';
  var root = document.getElementById('promo-page');
  if (!root || !window.UI) return;
  var slug = root.getAttribute('data-slug');
  var A = function (p) { return 'api/loja/' + slug + '/' + p; };

  // ---------------------------------------------------------------- abas
  var loaded = {};
  function openTab(name) {
    root.querySelectorAll('.ui-tab').forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('.ui-tabpanel').forEach(function (p) {
      var on = p.getAttribute('data-panel') === name;
      p.classList.toggle('is-active', on);
      p.hidden = !on;
    });
    if (!loaded[name]) {
      loaded[name] = true;
      ({ produtos: loadProducts, cupons: loadCoupons, parcelamento: simulate, frete: loadShipping, descontos: loadDiscounts })[name]();
    }
    try { history.replaceState(null, '', '#' + name); } catch (e) {}
  }
  root.querySelectorAll('.ui-tab').forEach(function (t) {
    t.addEventListener('click', function () { openTab(t.getAttribute('data-tab')); });
  });

  // ---------------------------------------------------------------- produtos
  var products = [];
  var showOnly = 'todos';
  function num(v) { var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? null : n; }

  function renderProducts() {
    var q = (document.getElementById('pp-search').value || '').toLowerCase();
    var list = products.filter(function (p) {
      if (showOnly === 'promo' && !p.promo_price) return false;
      return !q || String(p.name).toLowerCase().indexOf(q) >= 0;
    });
    var tb = document.getElementById('pp-rows');
    if (!list.length) {
      tb.innerHTML = '<tr><td colspan="7" class="ui-empty">' + (showOnly === 'promo' ? 'Nenhum produto em promoção.' : 'Nenhum produto encontrado.') + '</td></tr>';
      return;
    }
    tb.innerHTML = list.map(function (p) {
      var sale = Number(p.sale_price), cost = Number(p.cost_price || 0);
      var promo = p.promo_price ? Number(p.promo_price) : null;
      var eff = promo || sale;
      var pct = promo ? Math.round((1 - promo / sale) * 100) : 0;
      var margem = eff > 0 ? Math.round(((eff - cost) / eff) * 100) : 0;
      var img = p.images && p.images[0] && p.images[0].url ? '<img src="' + UI.esc(p.images[0].url) + '" alt="" loading="lazy">' : '';
      return '<tr data-id="' + p.id + '">' +
        '<td data-label="Produto"><div class="ui-prod">' + img + '<strong>' + UI.esc(p.name) + '</strong></div></td>' +
        '<td data-label="Custo" class="ui-num">' + UI.money(cost) + '</td>' +
        '<td data-label="Preço normal" class="ui-num">' + UI.money(sale) + '</td>' +
        '<td data-label="Promoção"><input class="ui-input-sm pp-input" type="number" step="0.01" min="0" placeholder="Sem promoção" value="' + (promo ? promo.toFixed(2) : '') + '" aria-label="Preço promocional de ' + UI.esc(p.name) + '"></td>' +
        '<td data-label="Desconto" class="ui-num">' + (pct ? '<span class="ui-badge ui-badge--danger">-' + pct + '%</span>' : '<span class="text-muted">—</span>') + '</td>' +
        '<td data-label="Margem" class="ui-num"><span class="' + (margem < 20 ? 'ui-text-warn' : '') + '">' + margem + '%</span></td>' +
        '<td class="ui-actions"><button type="button" class="btn btn-primary btn-sm pp-save">Salvar</button>' +
        (promo ? ' <button type="button" class="btn btn-secondary btn-sm pp-clear">Encerrar</button>' : '') + '</td>' +
      '</tr>';
    }).join('');
  }

  function loadProducts() {
    UI.api(A('products')).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      products = r.products || [];
      renderProducts();
    });
  }

  function savePromo(id, value) {
    return UI.api(A('products/' + id + '/promo'), { method: 'POST', body: { promo_price: value } }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      products.forEach(function (p) { if (Number(p.id) === Number(id)) p.promo_price = r.product.promo_price; });
      renderProducts();
      UI.toast(value ? 'Promoção salva.' : 'Promoção encerrada.', 'ok');
    });
  }

  document.getElementById('pp-rows').addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    var id = tr.getAttribute('data-id');
    if (e.target.closest('.pp-save')) {
      var v = num(tr.querySelector('.pp-input').value);
      savePromo(id, v && v > 0 ? v : null);
    } else if (e.target.closest('.pp-clear')) {
      savePromo(id, null);
    }
  });
  document.getElementById('pp-rows').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.classList.contains('pp-input')) {
      e.preventDefault();
      e.target.closest('tr').querySelector('.pp-save').click();
    }
  });
  document.getElementById('pp-search').addEventListener('input', renderProducts);
  root.querySelectorAll('.ui-seg-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      showOnly = b.getAttribute('data-show');
      root.querySelectorAll('.ui-seg-btn').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      renderProducts();
    });
  });

  // ---------------------------------------------------------------- cupons
  function renderCoupons(list) {
    var tb = document.getElementById('cp-rows');
    if (!list.length) {
      tb.innerHTML = '<tr><td colspan="6" class="ui-empty">Nenhum cupom criado.</td></tr>';
      return;
    }
    var hoje = new Date().toISOString().slice(0, 10);
    tb.innerHTML = list.map(function (c) {
      var desc = c.type === 'percent' ? Number(c.value).toLocaleString('pt-BR') + '%' : UI.money(c.value);
      var regras = [];
      if (Number(c.min_subtotal) > 0) regras.push('mín. ' + UI.money(c.min_subtotal));
      if (c.ends_at) regras.push('até ' + c.ends_at.split('-').reverse().join('/'));
      var expirado = c.ends_at && c.ends_at < hoje;
      var esgotado = c.max_uses !== null && Number(c.uses) >= Number(c.max_uses);
      var sit = !Number(c.active) ? ['Desativado', 'muted'] : expirado ? ['Expirado', 'warn'] : esgotado ? ['Esgotado', 'warn'] : ['Ativo', 'ok'];
      return '<tr data-id="' + c.id + '">' +
        '<td data-label="Código"><code class="ui-code">' + UI.esc(c.code) + '</code></td>' +
        '<td data-label="Desconto">' + desc + '</td>' +
        '<td data-label="Regras" class="text-muted">' + (regras.join(' · ') || 'Sem restrições') + '</td>' +
        '<td data-label="Usos" class="ui-num">' + c.uses + (c.max_uses !== null ? ' / ' + c.max_uses : '') + '</td>' +
        '<td data-label="Situação"><span class="ui-badge ui-badge--' + sit[1] + '">' + sit[0] + '</span></td>' +
        '<td class="ui-actions">' +
          '<button type="button" class="btn btn-secondary btn-sm cp-toggle" data-active="' + (Number(c.active) ? 0 : 1) + '">' + (Number(c.active) ? 'Desativar' : 'Ativar') + '</button> ' +
          '<button type="button" class="btn btn-ghost btn-sm cp-del" aria-label="Excluir cupom ' + UI.esc(c.code) + '">Excluir</button>' +
        '</td>' +
      '</tr>';
    }).join('');
  }
  function loadCoupons() {
    UI.api(A('cupons')).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      renderCoupons(r.coupons || []);
    });
  }
  document.getElementById('cp-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    var body = {
      code: f.code.value, type: f.type.value, value: num(f.value.value),
      min_subtotal: num(f.min_subtotal.value) || 0, max_uses: parseInt(f.max_uses.value, 10) || 0, ends_at: f.ends_at.value
    };
    UI.api(A('cupons'), { method: 'POST', body: body }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      f.reset();
      renderCoupons(r.coupons || []);
      UI.toast('Cupom criado.', 'ok');
    });
  });
  document.getElementById('cp-rows').addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    var id = tr.getAttribute('data-id');
    var t = e.target.closest('.cp-toggle');
    if (t) {
      UI.api(A('cupons/' + id + '/ativo'), { method: 'POST', body: { active: t.getAttribute('data-active') === '1' } }).then(loadCoupons);
    }
    if (e.target.closest('.cp-del')) {
      UI.confirm({ title: 'Excluir cupom', message: 'O cupom deixa de funcionar na vitrine. Pedidos que já o usaram não mudam.', confirm: 'Excluir', danger: true }).then(function (ok) {
        if (ok) UI.api(A('cupons/' + id + '/excluir'), { method: 'POST', body: {} }).then(loadCoupons);
      });
    }
  });

  // ---------------------------------------------------------------- parcelamento
  function simulate() {
    var v = num(document.getElementById('ip-sim-value').value) || 0;
    var tb = document.getElementById('ip-sim');
    if (v <= 0) { tb.innerHTML = ''; return; }
    UI.api(A('parcelamento?valor=' + v)).then(function (r) {
      if (r.error) { tb.innerHTML = '<tr><td colspan="4" class="ui-empty">' + UI.esc(r.error) + '</td></tr>'; return; }
      var linhas = (r.options || []).map(function (o) {
        return '<tr><td>' + o.parcelas + 'x ' + (o.sem_juros ? '<span class="ui-badge ui-badge--ok">sem juros</span>' : '') + '</td>' +
          '<td class="ui-num">' + UI.money(o.parcela) + '</td><td class="ui-num">' + UI.money(o.total) + '</td>' +
          '<td class="ui-num">' + (o.juros > 0 ? UI.money(o.juros) : '—') + '</td></tr>';
      });
      if (r.pix && r.pix.preco) {
        linhas.unshift('<tr class="ui-row-highlight"><td>PIX <span class="ui-badge ui-badge--ok">−' + fmtPct(r.pix.pct) + '</span></td>' +
          '<td class="ui-num">—</td><td class="ui-num">' + UI.money(r.pix.preco) + '</td><td class="ui-num">− ' + UI.money(v - r.pix.preco) + '</td></tr>');
      }
      tb.innerHTML = linhas.join('') || '<tr><td colspan="4" class="ui-empty">Valor abaixo da parcela mínima.</td></tr>';
      renderPixEquivalence(r.pix, r.rules);
    });
  }
  function fmtPct(n) { return Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%'; }

  // ---------------------------------------------------------------- desconto no PIX
  /**
   * Matemática financeira na tela: o desconto à vista visto como taxa de juros.
   * Dar d% no PIX é o mesmo que "cobrar" i% a.m. de quem parcela sem juros
   * (i resolve 1 − d = a(n,i)/n; cálculo no servidor, CashDiscount).
   */
  function renderPixEquivalence(pix, rules) {
    var box = document.getElementById('pix-equivalencia');
    if (!box || !pix) return;
    if (!pix.pct) { box.innerHTML = '<p class="text-muted">Sem desconto no PIX: à vista e parcelado sem juros custam o mesmo para o cliente.</p>'; return; }
    var i = Number(pix.taxa_equivalente);
    var n = pix.parcelas_sem_juros;
    var taxa = rules ? Number(rules.rate) : 0;
    var comparacao = taxa > 0
      ? (i > taxa
        ? 'É mais do que os ' + fmtPct(taxa) + ' a.m. que você cobra acima de ' + n + 'x: o desconto está generoso.'
        : 'Fica abaixo dos ' + fmtPct(taxa) + ' a.m. que você cobra acima de ' + n + 'x.')
      : '';
    box.innerHTML = '<p><strong>' + fmtPct(pix.pct) + ' no PIX equivale a ' + fmtPct(i) + ' ao mês</strong> em relação a ' +
      (n > 1 ? n + 'x sem juros' : 'pagar no cartão em 30 dias') + '. ' + UI.esc(comparacao) + '</p>' +
      '<p class="text-muted">Taxa i que iguala o valor presente: 1 − d = a(n, i) / n.</p>';
  }
  document.getElementById('pix-form').addEventListener('submit', function (e) {
    e.preventDefault();
    UI.api(A('store/pix-discount'), { method: 'POST', body: { pct: num(e.target.pct.value) || 0 } }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      UI.toast(r.pct > 0 ? 'Desconto de ' + fmtPct(r.pct) + ' no PIX salvo.' : 'Desconto no PIX desligado.', 'ok');
      simulate();
    });
  });

  // ---------------------------------------------------------------- frete
  function maskCep(el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '').slice(0, 8);
      el.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
  }
  function renderShipping(r) {
    var tb = document.getElementById('fr-rows');
    var rules = r.rules || [];
    var free = document.getElementById('fr-free-form').free_shipping_min;
    if (document.activeElement !== free) free.value = r.free_shipping_min != null ? Number(r.free_shipping_min).toFixed(2) : '';
    if (!rules.length) {
      tb.innerHTML = '<tr><td colspan="5" class="ui-empty">Nenhuma faixa: a entrega sai sem custo para qualquer CEP.</td></tr>';
      return;
    }
    tb.innerHTML = rules.map(function (f) {
      return '<tr data-id="' + f.id + '">' +
        '<td data-label="Faixa"><strong>' + UI.esc(f.name) + '</strong></td>' +
        '<td data-label="CEPs" class="ui-nowrap">' + UI.esc(f.cep_start) + ' a ' + UI.esc(f.cep_end) + '</td>' +
        '<td data-label="Valor" class="ui-num">' + (f.price > 0 ? UI.money(f.price) : '<span class="ui-badge ui-badge--ok">grátis</span>') + '</td>' +
        '<td data-label="Prazo" class="ui-num">' + (f.days != null ? f.days + ' dia' + (f.days === 1 ? '' : 's') : '—') + '</td>' +
        '<td class="ui-actions"><button type="button" class="btn btn-ghost btn-sm fr-del" aria-label="Excluir faixa ' + UI.esc(f.name) + '">Excluir</button></td>' +
      '</tr>';
    }).join('');
  }
  function loadShipping() {
    UI.api(A('frete/faixas')).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      renderShipping(r);
    });
  }
  var frForm = document.getElementById('fr-form');
  maskCep(frForm.cep_start);
  maskCep(frForm.cep_end);
  frForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    UI.api(A('frete/faixas'), { method: 'POST', body: { name: f.name.value, cep_start: f.cep_start.value, cep_end: f.cep_end.value, price: num(f.price.value) || 0, days: f.days.value } }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      f.reset();
      renderShipping(r);
      UI.toast('Faixa de frete adicionada.', 'ok');
    });
  });
  document.getElementById('fr-rows').addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-id]');
    if (!tr || !e.target.closest('.fr-del')) return;
    UI.confirm({ title: 'Excluir faixa de frete', message: 'Pedidos já feitos não mudam. CEPs desta faixa deixam de receber entrega se não caírem em outra.', confirm: 'Excluir', danger: true }).then(function (ok) {
      if (ok) UI.api(A('frete/faixas/' + tr.getAttribute('data-id') + '/excluir'), { method: 'POST', body: {} }).then(renderShipping);
    });
  });
  document.getElementById('fr-free-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = e.target.free_shipping_min.value;
    UI.api(A('frete/gratis'), { method: 'POST', body: { free_shipping_min: v === '' ? null : num(v) } }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      renderShipping(r);
      UI.toast(r.free_shipping_min ? 'Frete grátis a partir de ' + UI.money(r.free_shipping_min) + '.' : 'Frete grátis desligado.', 'ok');
    });
  });
  var testCep = document.getElementById('fr-test-cep');
  maskCep(testCep);
  testCep.addEventListener('input', function () {
    var out = document.getElementById('fr-test-result');
    var cep = testCep.value.replace(/\D/g, '');
    if (cep.length !== 8) { out.textContent = ''; return; }
    UI.api(A('frete?cep=' + cep + '&subtotal=0')).then(function (r) {
      if (r.error) { out.textContent = r.error; return; }
      var q = r.frete;
      out.textContent = q.sem_regras ? 'Sem faixas: entrega sem custo.'
        : !q.disponivel ? 'Fora das faixas: a loja não entrega neste CEP.'
        : q.regra + ': ' + (q.valor > 0 ? UI.money(q.valor) : 'grátis') + (q.prazo != null ? ', ' + q.prazo + ' dia(s)' : '');
    });
  });
  document.getElementById('ip-sim-value').addEventListener('input', simulate);
  document.getElementById('ip-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    UI.api(A('store/installments'), { method: 'POST', body: { max: +f.max.value, free: +f.free.value, rate: num(f.rate.value), min_value: num(f.min_value.value) } }).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      UI.toast('Regra de parcelamento salva.', 'ok');
      simulate();
    });
  });

  // ---------------------------------------------------------------- descontos do PDV
  function loadDiscounts() {
    UI.api(A('descontos/pendentes?historico=1')).then(function (r) {
      var tb = document.getElementById('dr-rows');
      if (r.error) { tb.innerHTML = '<tr><td colspan="7" class="ui-empty">' + UI.esc(r.error) + '</td></tr>'; return; }
      var all = (r.pending || []).concat(r.recent || []);
      if (!all.length) { tb.innerHTML = '<tr><td colspan="7" class="ui-empty">Nenhum desconto solicitado ainda.</td></tr>'; return; }
      var SIT = { pendente: ['Aguardando', 'warn'], aprovado: ['Aprovado', 'ok'], usado: ['Aplicado na venda', 'ok'], recusado: ['Recusado', 'danger'], cancelado: ['Cancelado / expirou', 'muted'] };
      tb.innerHTML = all.map(function (d) {
        var s = SIT[d.status] || [d.status, 'muted'];
        var pct = Number(d.subtotal) > 0 ? Math.round(Number(d.discount) / Number(d.subtotal) * 100) : 0;
        return '<tr>' +
          '<td data-label="Data" class="ui-nowrap">' + UI.esc(UI.dateTime(d.created_at)) + '</td>' +
          '<td data-label="Funcionário">' + UI.esc(d.requested_by_name || '—') + '</td>' +
          '<td data-label="Venda" class="ui-num">' + UI.money(d.subtotal) + '</td>' +
          '<td data-label="Desconto" class="ui-num">' + UI.money(d.discount) + ' <span class="text-muted">(' + pct + '%)</span></td>' +
          '<td data-label="Motivo">' + UI.esc(d.reason || '—') + '</td>' +
          '<td data-label="Situação"><span class="ui-badge ui-badge--' + s[1] + '">' + s[0] + '</span></td>' +
          '<td data-label="Decidido por">' + UI.esc(d.decided_by_name || '—') + '</td>' +
        '</tr>';
      }).join('');
    });
  }
  document.addEventListener('ui:discount-decided', function () { if (loaded.descontos) loadDiscounts(); });

  var start = (location.hash || '').replace('#', '');
  openTab(['produtos', 'cupons', 'parcelamento', 'frete', 'descontos'].indexOf(start) >= 0 ? start : 'produtos');
})();
