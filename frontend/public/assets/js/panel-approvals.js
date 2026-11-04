/**
 * Aprovação de desconto do PDV (só carregado para gerentes).
 *
 * Consulta os pedidos pendentes a cada poucos segundos e mostra um cartão fixo
 * no canto da tela, em qualquer página do painel, com Aprovar / Recusar.
 */
(function () {
  'use strict';
  var main = document.querySelector('.panel-main[data-store-slug]');
  if (!main || !window.UI) return;
  var slug = main.getAttribute('data-store-slug');
  var INTERVALO = 5000;
  var host = null;
  var vistos = {};
  var timer = null;

  function ensureHost() {
    if (host) return host;
    host = document.createElement('section');
    host.className = 'ui-approvals';
    host.setAttribute('aria-live', 'polite');
    host.setAttribute('aria-label', 'Descontos aguardando aprovação');
    document.body.appendChild(host);
    host.addEventListener('click', onClick);
    return host;
  }

  function render(list) {
    ensureHost();
    if (!list.length) { host.innerHTML = ''; host.classList.remove('is-open'); return; }
    host.classList.add('is-open');
    host.innerHTML = list.map(function (d) {
      var pct = Number(d.subtotal) > 0 ? Math.round(Number(d.discount) / Number(d.subtotal) * 100) : 0;
      var novo = !vistos[d.id];
      vistos[d.id] = true;
      return '<article class="ui-approval' + (novo ? ' is-new' : '') + '" data-id="' + d.id + '">' +
        '<header class="ui-approval-head">' + UI.icon('percent', 18) +
          '<div><strong>Desconto solicitado no PDV</strong><span>' + UI.esc(d.requested_by_name || 'Funcionário') + ' · ' + UI.esc(UI.dateTime(d.created_at).split(' ')[1] || '') + '</span></div>' +
        '</header>' +
        '<dl class="ui-approval-grid">' +
          '<div><dt>Venda</dt><dd>' + UI.money(d.subtotal) + '</dd></div>' +
          '<div><dt>Desconto</dt><dd class="ui-text-danger">− ' + UI.money(d.discount) + ' (' + pct + '%)</dd></div>' +
          '<div><dt>Total final</dt><dd><strong>' + UI.money(Number(d.subtotal) - Number(d.discount)) + '</strong></dd></div>' +
        '</dl>' +
        (d.reason ? '<p class="ui-approval-reason">Motivo: ' + UI.esc(d.reason) + '</p>' : '') +
        (d.items_summary ? '<p class="ui-approval-items">' + UI.esc(d.items_summary) + '</p>' : '') +
        '<div class="ui-approval-actions">' +
          '<button type="button" class="btn btn-secondary" data-decision="recusar">Recusar</button>' +
          '<button type="button" class="btn btn-primary" data-decision="aprovar">Aprovar</button>' +
        '</div>' +
      '</article>';
    }).join('');
  }

  function poll() {
    if (document.hidden) return;
    UI.api('api/loja/' + slug + '/descontos/pendentes').then(function (r) {
      if (r && !r.error) render(r.pending || []);
    });
  }

  function onClick(e) {
    var b = e.target.closest('[data-decision]');
    if (!b) return;
    var card = b.closest('[data-id]');
    var id = card.getAttribute('data-id');
    var dec = b.getAttribute('data-decision');
    card.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    UI.api('api/loja/' + slug + '/descontos/' + id + '/decidir', { method: 'POST', body: { decision: dec } }).then(function (r) {
      if (r.error) UI.toast(r.error, 'error');
      else UI.toast(dec === 'aprovar' ? 'Desconto aprovado. O PDV já pode finalizar a venda.' : 'Desconto recusado.', dec === 'aprovar' ? 'ok' : 'info');
      document.dispatchEvent(new CustomEvent('ui:discount-decided'));
      poll();
    });
  }

  poll();
  timer = setInterval(poll, INTERVALO);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
