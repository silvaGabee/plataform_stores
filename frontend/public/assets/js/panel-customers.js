/** Clientes da vitrine (gerente): quem mais compra e quem sumiu. */
(function () {
  'use strict';
  var root = document.getElementById('customers-page');
  if (!root || !window.UI) return;
  var slug = root.getAttribute('data-slug');
  var dados = null;
  var mostrar = 'todos';

  function stat(rotulo, valor, tipo) {
    return '<div class="ui-stat' + (tipo ? ' ui-stat--' + tipo : '') + '"><span class="ui-stat-label">' + UI.esc(rotulo) + '</span><span class="ui-stat-value">' + UI.esc(valor) + '</span></div>';
  }

  function render() {
    var todos = dados.clientes || [];
    var sumidos = todos.filter(function (c) { return c.sumido; });
    var receita = todos.reduce(function (s, c) { return s + Number(c.total); }, 0);
    document.getElementById('customers-stats').innerHTML =
      stat('Clientes com compra', String(todos.length)) +
      stat('Total comprado', UI.money(receita), 'ok') +
      stat('Sumidos há ' + dados.dias_sumido + ' dias ou mais', String(sumidos.length), sumidos.length ? 'warn' : '');
    document.getElementById('customers-note').textContent =
      dados.vendas_balcao_sem_cliente + ' venda(s) de balcão não entram aqui, porque não identificam o cliente.';

    var lista = mostrar === 'sumidos' ? sumidos.slice().sort(function (a, b) { return b.dias_sem_comprar - a.dias_sem_comprar; }) : todos;
    var rows = document.getElementById('customers-rows');
    if (!lista.length) {
      rows.innerHTML = '<tr><td colspan="5" class="ui-empty">' + (mostrar === 'sumidos' ? 'Nenhum cliente sumido.' : 'Ainda não há compras pela vitrine.') + '</td></tr>';
      return;
    }
    rows.innerHTML = lista.map(function (c) {
      return '<tr>' +
        '<td data-label="Cliente"><strong>' + UI.esc(c.nome) + '</strong><small class="sales-refund-note">' + UI.esc(c.email) + '</small></td>' +
        '<td data-label="Pedidos" class="ui-num">' + Number(c.pedidos) + '</td>' +
        '<td data-label="Total comprado" class="ui-num">' + UI.money(c.total) + '</td>' +
        '<td data-label="Ticket médio" class="ui-num">' + UI.money(c.ticket_medio) + '</td>' +
        '<td data-label="Última compra">' + UI.esc(UI.dateTime(c.ultima_compra)) + ' <span class="ui-badge ' + (c.sumido ? 'ui-badge--warn' : 'ui-badge--muted') + '">há ' + Number(c.dias_sem_comprar) + ' dias</span></td>' +
      '</tr>';
    }).join('');
  }

  root.querySelectorAll('.ui-seg-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      root.querySelectorAll('.ui-seg-btn').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      mostrar = b.getAttribute('data-show');
      if (dados) render();
    });
  });

  UI.api('api/loja/' + slug + '/clientes').then(function (r) {
    if (r.error) { UI.toast(r.error, 'error'); return; }
    dados = r;
    render();
  });
})();
