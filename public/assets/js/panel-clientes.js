(function () {
  if (typeof storeSlug === 'undefined') return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    return fetch(url, { headers: { 'Content-Type': 'application/json' } }).then(function (r) { return r.json(); });
  }
  function formatMoney(val) {
    var n = parseFloat(val) || 0;
    return 'R$ ' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }
  function load() {
    var el = document.getElementById('customers-list');
    if (!el) return;
    el.innerHTML = '<p>Carregando...</p>';
    api('/api/loja/' + storeSlug + '/reports/customers').then(function (res) {
      var list = res.customers || [];
      if (list.length === 0) {
        el.innerHTML = '<p>Nenhum cliente ainda. Os clientes aparecem aqui quando realizam compras na loja.</p>';
        return;
      }
      var html = '<table class="panel-table"><thead><tr><th>Nome</th><th>E-mail</th><th>Pedidos</th><th>Produtos comprados</th><th>Valor gasto</th></tr></thead><tbody>';
      list.forEach(function (c) {
        var ordersCount = parseInt(c.orders_count, 10) || 0;
        var productsCount = parseInt(c.products_count, 10) || 0;
        var totalSpent = parseFloat(c.total_spent) || 0;
        html += '<tr><td>' + (c.name || '—') + '</td><td>' + (c.email || '—') + '</td><td>' + ordersCount + '</td><td>' + productsCount + '</td><td>' + formatMoney(totalSpent) + '</td></tr>';
      });
      html += '</tbody></table>';
      el.innerHTML = html;
    }).catch(function () {
      el.innerHTML = '<p>Erro ao carregar clientes.</p>';
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
