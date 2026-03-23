(function () {
  if (typeof storeSlug === 'undefined') return;
  var readonly = (typeof window.panelStockReadonly !== 'undefined')
    ? window.panelStockReadonly === true
    : (typeof window.panelReadonly !== 'undefined' && window.panelReadonly);
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path, opt) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    return fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opt }).then(function (r) { return r.json(); });
  }
  function load() {
    api('/api/loja/' + storeSlug + '/products').then(function (res) {
      var list = res.products || [];
      api('/api/loja/' + storeSlug + '/products/low-stock').then(function (r2) {
        var el = document.getElementById('low-stock-count');
        if (el) el.textContent = (r2.products || []).length;
      });
      var listEl = document.getElementById('stock-list');
      if (!listEl) return;
      var html = list.map(function (p) {
        var low = parseInt(p.min_stock, 10) > 0 && parseInt(p.stock_quantity, 10) <= parseInt(p.min_stock, 10);
        var adj = readonly ? '' : ' <button type="button" class="btn btn-sm btn-adjust" data-id="' + p.id + '" data-name="' + (p.name || '') + '">Ajustar</button>';
        return '<div class="card" style="margin-bottom:0.5rem;padding:0.75rem;' + (low ? 'border-left:4px solid #dc2626' : '') + '">' +
          '<strong>' + (p.name || '') + '</strong> — Estoque: ' + p.stock_quantity + ' (mín: ' + p.min_stock + ')' + adj + '</div>';
      }).join('') || '<p>Nenhum produto.</p>';
      listEl.innerHTML = html;
      listEl.querySelectorAll('.btn-adjust').forEach(function (b) {
        b.addEventListener('click', function () {
          document.getElementById('adjust-product-id').value = this.dataset.id;
          document.getElementById('adjust-product-name').textContent = this.dataset.name;
          document.getElementById('adjust-modal').classList.remove('hidden');
        });
      });
    });
  }
  var modal = document.getElementById('adjust-modal');
  if (modal) {
    modal.querySelectorAll('.close-modal').forEach(function (b) {
      b.addEventListener('click', function () { modal.classList.add('hidden'); });
    });
  }
  var adjustForm = document.getElementById('adjust-form');
  if (adjustForm) adjustForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var id = document.getElementById('adjust-product-id').value;
    api('/api/loja/' + storeSlug + '/products/' + id + '/stock', {
      method: 'POST',
      body: JSON.stringify({
        type: document.getElementById('adjust-type').value,
        quantity: parseInt(document.getElementById('adjust-qty').value, 10),
        reason: document.getElementById('adjust-reason').value
      })
    }).then(function (res) {
      if (res.error) { alert(res.error); return; }
      modal.classList.add('hidden');
      load();
    });
  });
  var btnDelete = document.getElementById('btn-delete-product-from-stock');
  if (btnDelete) {
    btnDelete.addEventListener('click', function () {
      var idEl = document.getElementById('adjust-product-id');
      var id = idEl ? idEl.value : '';
      var nameEl = document.getElementById('adjust-product-name');
      var name = nameEl ? nameEl.textContent : '';
      if (!id) {
        alert('Selecione um produto (clique em Ajustar no produto que deseja excluir).');
        return;
      }
      if (!confirm('Excluir o produto "' + name + '"? Esta ação não pode ser desfeita.')) return;
      var url = (base.replace(/\/$/, '') || '') + '/api/loja/' + storeSlug + '/products/delete';
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: parseInt(id, 10) || id })
      }).then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try { data = text ? JSON.parse(text) : {}; } catch (e) {}
          if (!r.ok) {
            var errMsg = (data && data.error) ? data.error : (text || 'Erro ' + r.status);
            throw new Error(errMsg);
          }
          return data;
        });
      }).then(function (res) {
        if (res && res.error) { alert(res.error); return; }
        if (modal) modal.classList.add('hidden');
        load();
      }).catch(function (err) {
        alert('Erro ao excluir: ' + (err.message || err));
      });
    });
  }
  load();
})();
