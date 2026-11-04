/**
 * Área do cliente: prazo de pagamento, copiar PIX, cancelar pedido e
 * acompanhar a confirmação sem recarregar a página.
 */
(function () {
  'use strict';
  if (!window.UI) return;

  function fmtRestante(ms) {
    if (ms <= 0) return 'Prazo encerrado';
    var s = Math.floor(ms / 1000);
    var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
    if (d > 0) return 'Reservado por mais ' + d + (d === 1 ? ' dia' : ' dias') + (h ? ' e ' + h + ' h' : '');
    if (h > 0) return 'Pague em até ' + h + ' h ' + String(m).padStart(2, '0') + ' min';
    return 'Pague em até ' + String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
  }

  /** O relógio do servidor manda: compensa a diferença para o do navegador. */
  function countdown(el, expires, serverNow, onEnd) {
    var exp = UI.parseDate(expires), now = UI.parseDate(serverNow);
    if (!exp || !now) { el.textContent = ''; return; }
    var skew = now.getTime() - Date.now();
    function tick() {
      var ms = exp.getTime() - (Date.now() + skew);
      el.textContent = fmtRestante(ms);
      el.classList.toggle('is-urgent', ms > 0 && ms < 5 * 60000);
      if (ms <= 0) { clearInterval(t); if (onEnd) onEnd(); }
    }
    var t = setInterval(tick, 1000);
    tick();
  }

  document.querySelectorAll('.ui-order-countdown[data-expires]').forEach(function (el) {
    countdown(el, el.getAttribute('data-expires'), el.getAttribute('data-now'));
  });

  document.addEventListener('click', function (e) {
    var c = e.target.closest('.js-copy');
    if (!c) return;
    var input = document.getElementById(c.getAttribute('data-target'));
    if (!input) return;
    var done = function () { UI.toast('Código PIX copiado.', 'ok'); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(input.value).then(done, function () { input.select(); document.execCommand('copy'); done(); });
    } else {
      input.select();
      document.execCommand('copy');
      done();
    }
  });

  function cancelOrder(slug, id) {
    return UI.confirm({
      title: 'Cancelar pedido #' + id + '?',
      message: 'As peças voltam para a venda e nenhum valor será cobrado. Se você já pagou o PIX, não cancele: aguarde a confirmação da loja.',
      confirm: 'Cancelar pedido', cancel: 'Manter pedido', danger: true
    }).then(function (ok) {
      if (!ok) return false;
      return UI.api('api/loja/' + slug + '/meus-pedidos/' + id + '/cancelar', { method: 'POST', body: {} }).then(function (r) {
        if (r.error) { UI.toast(r.error, 'error'); return false; }
        UI.toast('Pedido cancelado.', 'ok');
        return true;
      });
    });
  }

  var lista = document.getElementById('meus-pedidos');
  if (lista) {
    lista.addEventListener('click', function (e) {
      var b = e.target.closest('.js-cancel-order');
      if (!b) return;
      var id = b.closest('[data-id]').getAttribute('data-id');
      cancelOrder(lista.getAttribute('data-slug'), id).then(function (ok) { if (ok) location.reload(); });
    });
  }

  var box = document.getElementById('pagamento');
  if (box) {
    var slug = box.getAttribute('data-slug');
    var id = box.getAttribute('data-order');
    var cd = document.getElementById('paybox-countdown');
    countdown(cd, box.getAttribute('data-expires'), box.getAttribute('data-now'), function () {
      setTimeout(function () { location.reload(); }, 1500);
    });
    var cancel = document.getElementById('paybox-cancel');
    if (cancel) {
      cancel.addEventListener('click', function () {
        cancelOrder(slug, id).then(function (ok) { if (ok) location.reload(); });
      });
    }
    if (location.hash === '#pagamento') box.scrollIntoView({ block: 'start' });
    if (box.getAttribute('data-logged') === '1') {
      var poll = setInterval(function () {
        if (document.hidden) return;
        UI.api('api/loja/' + slug + '/meus-pedidos/' + id + '/pagamento').then(function (r) {
          if (!r || r.error || !r.order) return;
          if (r.order.status !== 'pendente') {
            clearInterval(poll);
            var st = document.getElementById('paybox-status');
            if (st) st.textContent = r.order.status === 'pago' ? 'Pagamento confirmado. Atualizando…' : 'Pedido cancelado. Atualizando…';
            setTimeout(function () { location.reload(); }, 1200);
          }
        });
      }, 5000);
    }
  }
})();
