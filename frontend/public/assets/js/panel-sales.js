/** Vendas e estornos (gerente). As regras do estorno ficam em OrderService::refundOrder. */
(function () {
  'use strict';
  var root = document.getElementById('sales-page');
  if (!root || !window.UI) return;
  var A = function (p) { return 'api/loja/' + root.getAttribute('data-slug') + '/' + p; };
  var METODO = { pix: 'PIX', cartao: 'Cartão', dinheiro: 'Dinheiro' };
  var status = '';
  var rows = document.getElementById('sales-rows');
  var busca = document.getElementById('sales-q');

  function situacao(o) {
    if (o.status !== 'estornado') return '<span class="ui-badge ui-badge--ok">Paga</span>';
    var detalhe = 'Estornada em ' + UI.dateTime(o.refunded_at) + (o.refunded_by_name ? ' por ' + o.refunded_by_name : '');
    return '<span class="ui-badge ui-badge--muted">Estornada</span><small class="sales-refund-note">' + UI.esc(detalhe) + '<br>' + UI.esc(o.refund_reason || '') + '</small>';
  }

  function render(lista) {
    if (!lista.length) {
      rows.innerHTML = '<tr><td colspan="8" class="ui-empty">Nenhuma venda encontrada.</td></tr>';
      return;
    }
    rows.innerHTML = lista.map(function (o) {
      return '<tr data-id="' + Number(o.id) + '" data-method="' + UI.esc(o.payment_method || '') + '" data-total="' + UI.esc(o.total) + '">' +
        '<td data-label="Pedido"><strong>#' + Number(o.id) + '</strong></td>' +
        '<td data-label="Data">' + UI.esc(UI.dateTime(o.created_at)) + '</td>' +
        '<td data-label="Cliente">' + UI.esc(o.customer_name || 'Cliente') + '</td>' +
        '<td data-label="Canal">' + (o.order_type === 'pdv' ? 'Balcão' : 'Vitrine') + '</td>' +
        '<td data-label="Pagamento">' + UI.esc(METODO[o.payment_method] || '—') + '</td>' +
        '<td data-label="Total" class="ui-num">' + UI.money(o.total) + '</td>' +
        '<td data-label="Situação">' + situacao(o) + '</td>' +
        '<td class="ui-actions">' + (o.status === 'pago' ? '<button type="button" class="btn btn-secondary btn-sm sales-refund">Estornar</button>' : '') + '</td>' +
      '</tr>';
    }).join('');
  }

  function load() {
    var q = 'orders/vendas?q=' + encodeURIComponent(busca.value.trim()) + '&status=' + encodeURIComponent(status);
    return UI.api(A(q)).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      render(r.orders || []);
    });
  }

  function avisoDinheiro(metodo) {
    return metodo === 'dinheiro'
      ? 'O valor sai do caixa aberto como uma retirada. Sem caixa aberto, o estorno é recusado.'
      : 'O sistema só registra o estorno: devolva o valor ao cliente por ' + (metodo === 'pix' ? 'PIX' : 'cartão') + ', fora daqui.';
  }

  function estornar(tr) {
    var id = tr.getAttribute('data-id');
    var metodo = tr.getAttribute('data-method');
    UI.api(A('orders/' + id)).then(function (pedido) {
      if (pedido.error) { UI.toast(pedido.error, 'error'); return; }
      var itens = (pedido.items || []).map(function (i) {
        var variacao = i.variant_key ? ' (' + String(i.variant_key).replace('|', ' · ') + ')' : '';
        return '<li>' + Number(i.quantity) + ' × ' + UI.esc(i.product_name) + UI.esc(variacao) + '</li>';
      }).join('');
      return UI.confirm({
        title: 'Estornar o pedido #' + id,
        html:
          '<p>Valor: <strong>' + UI.money(tr.getAttribute('data-total')) + '</strong>. ' + UI.esc(avisoDinheiro(metodo)) + '</p>' +
          '<ul class="sales-refund-items">' + itens + '</ul>' +
          '<label class="ui-field"><span>Motivo</span><input id="sales-reason" maxlength="255" placeholder="Ex.: venda lançada errada" autocomplete="off"></label>' +
          '<label class="ui-field"><span>As peças voltam ao estoque?</span><select id="sales-stock"><option value="1">Sim, podem ser vendidas de novo</option><option value="0">Não: com defeito, ou o cliente ficou com elas</option></select></label>',
        confirm: 'Estornar a venda',
        cancel: 'Voltar',
        danger: true,
        returnValues: true
      }).then(function (v) {
        if (!v) return;
        if (!String(v['sales-reason'] || '').trim()) { UI.toast('Informe o motivo do estorno.', 'error'); return; }
        return UI.api(A('orders/' + id + '/estornar'), {
          method: 'POST',
          body: { reason: v['sales-reason'], return_to_stock: v['sales-stock'] === '1' }
        }).then(function (r) {
          if (r.error) { UI.toast(r.error, 'error'); return; }
          if (r.nao_repostos && r.nao_repostos.length) {
            UI.toast('Pedido #' + id + ' estornado. Sem variação para repor, ajuste à mão: ' + r.nao_repostos.join(', ') + '.', 'info', 9000);
          } else {
            UI.toast('Pedido #' + id + ' estornado.', 'ok');
          }
          load();
        });
      });
    });
  }

  rows.addEventListener('click', function (e) {
    var b = e.target.closest('.sales-refund');
    if (b) estornar(b.closest('tr'));
  });
  root.querySelectorAll('.ui-seg-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      root.querySelectorAll('.ui-seg-btn').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      status = b.getAttribute('data-status');
      load();
    });
  });
  var timer = null;
  busca.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(load, 250);
  });
  load();
})();
