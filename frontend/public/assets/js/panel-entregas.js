(function () {
  if (typeof storeSlug === 'undefined') return;

  function getBase() {
    if (typeof window.PANEL_BASE_URL === 'string' && window.PANEL_BASE_URL) return window.PANEL_BASE_URL.replace(/\/$/, '');
    var meta = document.querySelector('meta[name="base-url"]');
    var base = (meta && meta.getAttribute('content')) ? meta.getAttribute('content').trim() : '';
    if (base) return base.replace(/\/$/, '');
    var pathname = window.location.pathname || '';
    var idx = pathname.indexOf('/painel/');
    if (idx !== -1) return window.location.origin + pathname.substring(0, idx);
    return window.location.origin;
  }

  var base = getBase();
  var modal = document.getElementById('entregas-modal-tracking');
  var trackingInput = document.getElementById('entregas-tracking-input');
  var trackingConfirm = document.getElementById('entregas-tracking-confirm');
  var pendingDrop = null;

  function api(path, options) {
    var p = path.replace(/^\//, '');
    var url = base ? (base.replace(/\/$/, '') + '/' + p) : (window.location.origin + '/' + p);
    return fetch(url, { headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', ...options })
      .then(function (r) {
        if (!r.ok) return r.text().then(function (t) { throw new Error(t); });
        return r.json();
      });
  }

  function getColId(type, stage) {
    var prefix = type === 'retirada' ? 'retira' : 'entrega';
    var s = stage === 'entregue_transportadora' ? 'transportadora' : (stage === 'em_rota' ? 'em-rota' : stage);
    return prefix + '-' + s;
  }

  function renderCard(order) {
    var type = (order.delivery_type || 'retirada').toLowerCase();
    var stage = order.delivery_stage || 'solicitado';
    var card = document.createElement('div');
    card.className = 'entregas-card';
    card.draggable = true;
    card.dataset.orderId = order.id;
    card.dataset.type = type;
    card.dataset.stage = stage;
    var total = typeof order.total === 'number' ? order.total : parseFloat(order.total) || 0;
    var totalStr = 'R$ ' + total.toFixed(2).replace('.', ',');
    card.innerHTML = '<strong>#' + order.id + '</strong> ' + (order.customer_name || 'Cliente') + ' — ' + totalStr +
      (order.tracking_code ? '<br><small>Código: ' + escapeHtml(order.tracking_code) + '</small>' : '');
    return card;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function placeCards(orders) {
    var ids = {
      retira: { solicitado: 'retira-solicitado', entregue: 'retira-entregue' },
      retirada: { solicitado: 'retira-solicitado', entregue: 'retira-entregue' },
      entrega: {
        solicitado: 'entrega-solicitado',
        empacotando: 'entrega-empacotando',
        entregue_transportadora: 'entrega-transportadora',
        em_rota: 'entrega-em-rota',
        entregue: 'entrega-entregue'
      }
    };
    ['retira-solicitado', 'retira-entregue', 'entrega-solicitado', 'entrega-empacotando', 'entrega-transportadora', 'entrega-em-rota', 'entrega-entregue'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.innerHTML = '';
    });
    (orders || []).forEach(function (order) {
      var type = (order.delivery_type || 'retirada').toLowerCase();
      var stage = (order.delivery_stage || 'solicitado').toLowerCase();
      var typeKey = type === 'retirada' ? 'retira' : type;
      var colId = (ids[typeKey] && ids[typeKey][stage]) ? ids[typeKey][stage] : (type === 'entrega' ? ids.entrega.solicitado : ids.retira.solicitado);
      var col = document.getElementById(colId);
      if (col) col.appendChild(renderCard(order));
    });
  }

  function setupDragAndDrop() {
    document.querySelectorAll('.entregas-card').forEach(function (card) {
      card.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', card.dataset.orderId);
        e.dataTransfer.effectAllowed = 'move';
        card.classList.add('entregas-dragging');
      });
      card.addEventListener('dragend', function () {
        card.classList.remove('entregas-dragging');
      });
    });

    document.querySelectorAll('.entregas-col[data-droppable="true"]').forEach(function (col) {
      col.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('entregas-drop-over');
      });
      col.addEventListener('dragleave', function () {
        col.classList.remove('entregas-drop-over');
      });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('entregas-drop-over');
        var orderId = e.dataTransfer.getData('text/plain');
        if (!orderId) return;
        var targetType = col.dataset.type;
        var targetStage = col.dataset.stage;
        var card = document.querySelector('.entregas-card[data-order-id="' + orderId + '"]');
        if (!card) return;
        var orderType = card.dataset.type;
        if (orderType === 'retirada' && targetType !== 'retirada') return;
        if (orderType === 'retirada' && targetStage !== 'solicitado' && targetStage !== 'entregue') return;

        if (targetStage === 'em_rota' && orderType === 'entrega') {
          pendingDrop = { orderId: orderId, stage: targetStage };
          if (modal) {
            modal.classList.remove('hidden');
            if (trackingInput) { trackingInput.value = ''; trackingInput.focus(); }
          } else {
            var code = prompt('Código de rastreio da transportadora:');
            if (code != null && code.trim() !== '') doUpdate(orderId, targetStage, code.trim());
          }
        } else {
          doUpdate(orderId, targetStage, null);
        }
      });
    });
  }

  function doUpdate(orderId, stage, trackingCode) {
    var payload = { stage: stage };
    if (trackingCode) payload.tracking_code = trackingCode;
    api('api/loja/' + storeSlug + '/orders/' + orderId + '/delivery-stage', {
      method: 'POST',
      body: JSON.stringify(payload)
    }).then(function (res) {
      var orders = window._entregasOrders || [];
      var updated = res.order;
      if (!updated) return;
      // Garantir que o pedido retornado tenha stage e type (backend pode vir com snake_case)
      updated.delivery_stage = updated.delivery_stage || updated.deliveryStage || stage;
      updated.delivery_type = updated.delivery_type || updated.deliveryType || 'retirada';
      var idx = orders.findIndex(function (o) { return String(o.id) === String(orderId); });
      if (idx >= 0) {
        // Manter customer_name (e outros campos de exibição) se a resposta não trouxer
        if (!updated.customer_name && orders[idx].customer_name) updated.customer_name = orders[idx].customer_name;
        orders[idx] = updated;
      } else {
        orders.push(updated);
      }
      window._entregasOrders = orders;
      placeCards(orders);
      setupDragAndDrop();
    }).catch(function (err) {
      alert('Erro: ' + (err.message || err));
    });
  }

  if (trackingConfirm && modal) {
    trackingConfirm.addEventListener('click', function () {
      var code = trackingInput && trackingInput.value ? trackingInput.value.trim() : '';
      if (!code) {
        alert('Informe o código de rastreio.');
        return;
      }
      if (pendingDrop) {
        doUpdate(pendingDrop.orderId, pendingDrop.stage, code);
        pendingDrop = null;
      }
      modal.classList.add('hidden');
    });
  }
  document.querySelectorAll('.close-entregas-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (modal) modal.classList.add('hidden');
      pendingDrop = null;
    });
  });

  api('api/loja/' + storeSlug + '/orders/entregas').then(function (res) {
    var orders = res.orders || [];
    window._entregasOrders = orders;
    placeCards(orders);
    setupDragAndDrop();
  }).catch(function (err) {
    console.error(err);
    var msg = err && err.message ? err.message : 'Erro ao carregar.';
    var el = document.querySelector('.entregas-kanban');
    if (el) el.innerHTML = '<p class="text-muted">Erro ao carregar pedidos: ' + escapeHtml(msg) + '. Verifique a URL da página (deve ser pelo mesmo domínio do servidor, ex.: http://localhost/.../painel/...).</p>';
  });
})();
