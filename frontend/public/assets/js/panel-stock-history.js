/** Histórico de estoque do gerente: filtros, totais, paginação e CSV. */
(function () {
  'use strict';
  var root = document.getElementById('stock-history');
  if (!root || !window.UI) return;
  var slug = root.getAttribute('data-slug');
  var form = document.getElementById('sh-filters');
  var rows = document.getElementById('sh-rows');
  var page = 1;
  var lastTotal = 0;
  var TIPOS = {
    entrada: ['Entrada', 'ok'],
    saida: ['Venda', 'info'],
    ajuste: ['Ajuste', 'warn'],
    devolucao: ['Devolução', 'ok']
  };

  function params(extra) {
    var fd = new FormData(form);
    var p = new URLSearchParams();
    fd.forEach(function (v, k) { if (String(v).trim() !== '') p.set(k, String(v).trim()); });
    Object.keys(extra || {}).forEach(function (k) { p.set(k, extra[k]); });
    return p.toString();
  }

  function fmt(n) { return Number(n || 0).toLocaleString('pt-BR'); }

  function load() {
    rows.innerHTML = '<tr><td colspan="6" class="ui-empty">Carregando…</td></tr>';
    UI.api('api/loja/' + slug + '/stock-movements/search?' + params({ page: page })).then(function (r) {
      if (r.error) {
        rows.innerHTML = '<tr><td colspan="6" class="ui-empty">' + UI.esc(r.error) + '</td></tr>';
        return;
      }
      lastTotal = r.total;
      document.getElementById('sh-total').textContent = fmt(r.total);
      document.getElementById('sh-in').textContent = fmt(r.totais.entradas);
      document.getElementById('sh-out').textContent = fmt(r.totais.saidas);
      document.getElementById('sh-adj').textContent = fmt(r.totais.ajustes);
      var sel = document.getElementById('sh-users');
      if (sel.options.length <= 1 && r.users) {
        r.users.forEach(function (u) {
          var o = document.createElement('option');
          o.value = u.id; o.textContent = u.name;
          sel.appendChild(o);
        });
      }
      if (!r.rows.length) {
        rows.innerHTML = '<tr><td colspan="6" class="ui-empty">Nenhuma movimentação com esses filtros.</td></tr>';
      } else {
        rows.innerHTML = r.rows.map(function (m) {
          var t = TIPOS[m.type] || [m.type, 'info'];
          var sinal = (m.type === 'saida' || m.type === 'ajuste') ? '−' : '+';
          return '<tr>' +
            '<td data-label="Data" class="ui-nowrap">' + UI.esc(UI.dateTime(m.created_at)) + '</td>' +
            '<td data-label="Produto"><strong>' + UI.esc(m.product_name) + '</strong></td>' +
            '<td data-label="Tipo"><span class="ui-badge ui-badge--' + t[1] + '">' + UI.esc(t[0]) + '</span></td>' +
            '<td data-label="Qtd." class="ui-num ui-qty ui-qty--' + t[1] + '">' + sinal + fmt(m.quantity) + '</td>' +
            '<td data-label="Motivo">' + UI.esc(m.reason || '—') + '</td>' +
            '<td data-label="Responsável">' + UI.esc(m.user_name || 'Sistema') +
              (m.user_name && !m.user_role ? ' <span class="ui-badge ui-badge--muted">cliente · vitrine</span>' : '') + '</td>' +
          '</tr>';
        }).join('');
      }
      var ini = r.total ? (page - 1) * r.per_page + 1 : 0;
      var fim = Math.min(page * r.per_page, r.total);
      document.getElementById('sh-range').textContent = r.total ? ('Mostrando ' + fmt(ini) + '–' + fmt(fim) + ' de ' + fmt(r.total)) : '';
      document.getElementById('sh-prev').disabled = page <= 1;
      document.getElementById('sh-next').disabled = fim >= r.total;
    });
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); page = 1; load(); });
  form.addEventListener('reset', function () { setTimeout(function () { page = 1; load(); }, 0); });
  document.getElementById('sh-prev').addEventListener('click', function () { if (page > 1) { page--; load(); } });
  document.getElementById('sh-next').addEventListener('click', function () { page++; load(); });
  document.getElementById('sh-export').addEventListener('click', function (e) {
    e.preventDefault();
    if (!lastTotal) { UI.toast('Nada para exportar com esses filtros.', 'info'); return; }
    window.location.href = UI.base + '/api/loja/' + slug + '/stock-movements/search?' + params({ formato: 'csv' });
  });
  load();
})();
