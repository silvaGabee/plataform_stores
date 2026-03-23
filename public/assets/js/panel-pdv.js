(function () {
  if (typeof storeSlug === 'undefined') return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path, opt) {
    return fetch(base.replace(/\/$/, '') + path, { headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', ...opt }).then(function (r) { return r.json(); });
  }
  var cart = [];

  function showGreeting() {
    var el = document.getElementById('pdv-greeting');
    if (!el) return;
    var name = (typeof pdvUserName !== 'undefined' && pdvUserName) ? pdvUserName : 'usuário';
    el.textContent = 'Olá ' + name + ', Boas Vendas!!!';
    el.classList.remove('hidden');
  }
  function hideGreeting() {
    var el = document.getElementById('pdv-greeting');
    if (el) el.classList.add('hidden');
  }

  function formatMoney(n) {
    return (n != null && !isNaN(n) ? Number(n) : 0).toFixed(2).replace('.', ',');
  }

  function refreshCashBalance() {
    api('/api/loja/' + storeSlug + '/cash/status').then(function (status) {
      if (!status || !status.open) return;
      var span = document.getElementById('pdv-cash-balance');
      if (span && status.balance != null) {
        span.textContent = formatMoney(status.balance);
      } else if (status.open) {
        renderCashOpen(status);
      }
    });
  }

  function renderCashOpen(res) {
    var el = document.getElementById('cash-status');
    var balance = res.balance != null ? formatMoney(res.balance) : '0,00';
    el.className = 'pdv-cash-status pdv-cash-open';
    el.innerHTML = '<p>Caixa aberto · Saldo: <strong>R$ <span id="pdv-cash-balance">' + balance + '</span></strong></p>' +
      '<button type="button" class="btn btn-secondary" id="btn-close-cash">Fechar caixa</button>';
    document.getElementById('btn-close-cash').addEventListener('click', function () {
      var amt = prompt('Valor em caixa ao fechar:');
      if (amt == null) return;
      var cashId = (res.open && res.open.id) ? res.open.id : 0;
      api('/api/loja/' + storeSlug + '/cash/close', {
        method: 'POST',
        body: JSON.stringify({ cash_register_id: cashId, final_amount: parseFloat(amt) || 0 })
      }).then(function (r) {
        if (r.success) {
          hideGreeting();
          renderCashClosed();
        } else alert(r.error);
      });
    });
    showGreeting();
  }

  function renderCashClosed() {
    var el = document.getElementById('cash-status');
    el.className = 'pdv-cash-status';
    el.innerHTML = '<p>Caixa fechado.</p><button type="button" class="btn btn-primary" id="btn-open-cash">Abrir caixa</button>';
    document.getElementById('btn-open-cash').addEventListener('click', function () {
      api('/api/loja/' + storeSlug + '/cash/open', {
        method: 'POST',
        body: JSON.stringify({ initial_amount: 0 })
      }).then(function (r) {
        if (r.success) {
          api('/api/loja/' + storeSlug + '/cash/status').then(function (status) {
            if (status.open) renderCashOpen(status);
            else alert('Caixa não abriu. Atualize a página.');
          });
        } else alert(r.error);
      });
    });
    hideGreeting();
  }

  function renderCart() {
    var total = 0;
    var ul = document.getElementById('pdv-cart-items');
    var emptyEl = document.getElementById('pdv-cart-empty');
    if (!ul) return;
    ul.innerHTML = cart.map(function (item) {
      var subtotal = item.price * item.qty;
      total += subtotal;
      return '<li class="pdv-cart-item"><span class="pdv-cart-item-name">' + (item.name || '').replace(/</g, '&lt;') + '</span><span class="pdv-cart-item-qty">' + item.qty + ' x R$ ' + formatMoney(item.price) + '</span><span class="pdv-cart-item-sub">R$ ' + formatMoney(subtotal) + '</span><button type="button" class="pdv-cart-item-remove" data-id="' + item.product_id + '" title="Remover">×</button></li>';
    }).join('');
    if (emptyEl) emptyEl.classList.toggle('hidden', cart.length > 0);
    var totalEl = document.getElementById('pdv-total');
    if (totalEl) totalEl.textContent = total.toFixed(2).replace('.', ',');
    ul.querySelectorAll('.pdv-cart-item-remove').forEach(function (b) {
      b.addEventListener('click', function () {
        cart = cart.filter(function (i) { return i.product_id !== parseInt(this.dataset.id, 10); }.bind(this));
        renderCart();
      });
    });
  }

  function loadProducts(filter) {
    api('/api/loja/' + storeSlug + '/products?in_stock=1').then(function (res) {
      var list = (res.products || []).filter(function (p) {
        return !filter || (p.name || '').toLowerCase().indexOf(filter.toLowerCase()) !== -1;
      });
      document.getElementById('pdv-product-list').innerHTML = list.map(function (p) {
        var stock = parseInt(p.stock_quantity, 10);
        if (isNaN(stock) || stock < 0) stock = 0;
        var nameEsc = String(p.name || '').replace(/"/g, '&quot;');
        var priceStr = parseFloat(p.sale_price).toFixed(2).replace('.', ',');
        return '<button type="button" class="pdv-product-btn" data-id="' + p.id + '" data-name="' + nameEsc + '" data-price="' + p.sale_price + '" data-stock="' + stock + '"><span class="pdv-product-btn-name">' + (p.name || '').replace(/</g, '&lt;') + '</span><span class="pdv-product-btn-price">R$ ' + priceStr + '</span><span class="pdv-product-btn-stock">est. ' + stock + '</span></button>';
      }).join('');
      document.getElementById('pdv-product-list').querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          var id = parseInt(this.dataset.id, 10);
          var name = this.dataset.name;
          var price = parseFloat(this.dataset.price);
          var stockMax = parseInt(this.dataset.stock, 10);
          if (isNaN(stockMax) || stockMax < 0) stockMax = 0;
          var ex = cart.find(function (i) { return i.product_id === id; });
          if (ex) {
            if (ex.qty >= stockMax) {
              alert('Estoque máximo para este produto: ' + stockMax + '. Você já adicionou ' + ex.qty + '.');
              return;
            }
            ex.qty++;
          } else {
            if (stockMax < 1) {
              alert('Produto sem estoque disponível.');
              return;
            }
            cart.push({ product_id: id, name: name, price: price, qty: 1, stock_max: stockMax });
          }
          renderCart();
        });
      });
    });
  }

  document.getElementById('pdv-search').addEventListener('input', function () { loadProducts(this.value); });
  document.getElementById('pdv-finish').addEventListener('click', function () {
    if (cart.length === 0) { alert('Adicione itens à venda.'); return; }
    var customerName = document.getElementById('pdv-customer-name').value.trim() || 'Cliente PDV';
    api('/api/loja/' + storeSlug + '/cash/status').then(function (cashRes) {
      if (!cashRes.open) { alert('Abra o caixa antes de finalizar vendas.'); return; }
      api('/api/loja/' + storeSlug + '/users').then(function (userRes) {
        var firstUserId = (userRes.users && userRes.users[0]) ? userRes.users[0].id : 0;
        api('/api/loja/' + storeSlug + '/orders', {
          method: 'POST',
          body: JSON.stringify({
            order_type: 'pdv',
            customer_id: firstUserId,
            created_by: firstUserId,
            items: cart.map(function (i) { return { product_id: i.product_id, quantity: i.qty }; })
          })
        }).then(function (orderRes) {
          if (orderRes.error) { alert(orderRes.error); return; }
          var order = orderRes.order;
          api('/api/loja/' + storeSlug + '/payments', {
            method: 'POST',
            body: JSON.stringify({ order_id: order.id, method: 'dinheiro', amount: parseFloat(order.total) })
          }).then(function (payRes) {
            if (payRes.error) { alert(payRes.error); return; }
            var paymentId = payRes.payment && payRes.payment.id;
            if (!paymentId) { alert('Pagamento não criado'); return; }
            api('/api/loja/' + storeSlug + '/payments/confirm', {
              method: 'POST',
              body: JSON.stringify({ payment_id: paymentId })
            }).then(function (confirmRes) {
              if (confirmRes.error) alert(confirmRes.error);
              cart = [];
              renderCart();
              loadProducts(document.getElementById('pdv-search').value);
              refreshCashBalance();
              alert('Venda registrada. Pedido #' + order.id);
            });
          });
        });
      });
    });
  });

  api('/api/loja/' + storeSlug + '/cash/status').then(function (res) {
    if (res.open) renderCashOpen(res);
    else renderCashClosed();
  });
  loadProducts();
})();
