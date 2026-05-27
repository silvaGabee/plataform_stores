(function () {
  if (typeof storeSlug === 'undefined') return;

  var ENTREGUE_COLS = ['retira-entregue', 'entrega-entregue'];
  var selectedByCol = {};
  var removeModeByCol = {};
  ENTREGUE_COLS.forEach(function (id) {
    selectedByCol[id] = new Set();
    removeModeByCol[id] = false;
  });

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

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
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
    card.dataset.orderId = order.id;
    card.dataset.type = type;
    card.dataset.stage = stage;
    var total = typeof order.total === 'number' ? order.total : parseFloat(order.total) || 0;
    var totalStr = 'R$ ' + total.toFixed(2).replace('.', ',');
    var isEntregue = String(stage).toLowerCase() === 'entregue';

    if (isEntregue) {
      card.classList.add('entregas-card--selectable');
      card.draggable = false;
      card.innerHTML =
        '<label class="entregas-card-select" title="Selecionar pedido">' +
          '<input type="checkbox" class="entregas-card-checkbox" value="' + order.id + '" aria-label="Selecionar pedido #' + order.id + '">' +
          '<span class="entregas-card-checkmark" aria-hidden="true"></span>' +
        '</label>' +
        '<div class="entregas-card-body">' +
          '<div class="entregas-card-line"><strong>#' + order.id + '</strong> ' + escapeHtml(order.customer_name || 'Cliente') + '</div>' +
          '<div class="entregas-card-line entregas-card-total">' + totalStr + '</div>' +
          (order.tracking_code ? '<small class="entregas-card-tracking">Código: ' + escapeHtml(order.tracking_code) + '</small>' : '') +
        '</div>';
    } else {
      card.draggable = true;
      card.innerHTML =
        '<div class="entregas-card-body">' +
          '<div class="entregas-card-line"><strong>#' + order.id + '</strong> ' + escapeHtml(order.customer_name || 'Cliente') + '</div>' +
          '<div class="entregas-card-line entregas-card-total">' + totalStr + '</div>' +
          (order.tracking_code ? '<small class="entregas-card-tracking">Código: ' + escapeHtml(order.tracking_code) + '</small>' : '') +
        '</div>';
    }
    return card;
  }

  function getCardsColId(card) {
    var list = card && card.closest('.entregas-cards');
    return list ? list.id : '';
  }

  function enterRemoveMode(colId) {
    removeModeByCol[colId] = true;
    selectedByCol[colId] = new Set();
    var col = document.querySelector('.entregas-col[data-entregue-col="' + colId + '"]');
    if (col) col.classList.add('entregas-col--remove-mode');
    var idle = document.querySelector('[data-toolbar-idle="' + colId + '"]');
    var panel = document.querySelector('[data-toolbar-remove="' + colId + '"]');
    if (idle) idle.classList.add('hidden');
    if (panel) panel.classList.remove('hidden');
    updateToolbar(colId);
  }

  function exitRemoveMode(colId) {
    removeModeByCol[colId] = false;
    selectedByCol[colId] = new Set();
    var col = document.querySelector('.entregas-col[data-entregue-col="' + colId + '"]');
    if (col) col.classList.remove('entregas-col--remove-mode');
    var idle = document.querySelector('[data-toolbar-idle="' + colId + '"]');
    var panel = document.querySelector('[data-toolbar-remove="' + colId + '"]');
    if (idle) idle.classList.remove('hidden');
    if (panel) panel.classList.add('hidden');
    document.querySelectorAll('#' + colId + ' .entregas-card--selectable').forEach(function (card) {
      card.classList.remove('entregas-card--selected');
      var cb = card.querySelector('.entregas-card-checkbox');
      if (cb) cb.checked = false;
    });
    updateToolbar(colId);
  }

  function updateToolbar(colId) {
    if (!removeModeByCol[colId]) return;
    var set = selectedByCol[colId] || new Set();
    var n = set.size;
    var countEl = document.querySelector('[data-count-for="' + colId + '"]');
    var confirmBtn = document.querySelector('[data-confirm-remove-for="' + colId + '"]');
    var selectAllBtn = document.querySelector('[data-select-all-for="' + colId + '"]');
    if (countEl) {
      countEl.textContent = n === 0
        ? 'Nenhum selecionado'
        : (n === 1 ? '1 pedido selecionado' : n + ' pedidos selecionados');
    }
    if (confirmBtn) confirmBtn.disabled = n === 0;
    if (selectAllBtn) {
      var total = document.querySelectorAll('#' + colId + ' .entregas-card--selectable').length;
      selectAllBtn.textContent = n > 0 && n === total ? 'Desmarcar todos' : 'Selecionar todos';
    }
  }

  function setCardSelected(card, selected) {
    var colId = getCardsColId(card);
    if (!colId || !selectedByCol[colId]) return;
    var orderId = card.dataset.orderId;
    var cb = card.querySelector('.entregas-card-checkbox');
    if (selected) {
      selectedByCol[colId].add(orderId);
      card.classList.add('entregas-card--selected');
      if (cb) cb.checked = true;
    } else {
      selectedByCol[colId].delete(orderId);
      card.classList.remove('entregas-card--selected');
      if (cb) cb.checked = false;
    }
    updateToolbar(colId);
  }

  function toggleCardSelected(card) {
    var colId = getCardsColId(card);
    if (!colId || !removeModeByCol[colId]) return;
    var isOn = selectedByCol[colId].has(card.dataset.orderId);
    setCardSelected(card, !isOn);
  }

  function selectAllInCol(colId, select) {
    if (!removeModeByCol[colId]) return;
    document.querySelectorAll('#' + colId + ' .entregas-card--selectable').forEach(function (card) {
      setCardSelected(card, !!select);
    });
  }

  function removeSelectedFromCol(colId) {
    var ids = Array.from(selectedByCol[colId] || []);
    if (!ids.length) {
      alert('Selecione ao menos um pedido para remover.');
      return;
    }
    var msg = ids.length === 1
      ? 'Remover o pedido #' + ids[0] + ' do histórico? Esta ação não pode ser desfeita.'
      : 'Remover ' + ids.length + ' pedidos do histórico? Esta ação não pode ser desfeita.';
    if (!confirm(msg)) return;

    var confirmBtn = document.querySelector('[data-confirm-remove-for="' + colId + '"]');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Removendo...';
    }

    var chain = Promise.resolve();
    ids.forEach(function (orderId) {
      chain = chain.then(function () {
        return api('api/loja/' + storeSlug + '/orders/' + orderId + '/entregas/delete', {
          method: 'POST',
          body: JSON.stringify({})
        });
      });
    });

    chain.then(function () {
      var removed = new Set(ids.map(String));
      window._entregasOrders = (window._entregasOrders || []).filter(function (o) {
        return !removed.has(String(o.id));
      });
      exitRemoveMode(colId);
      placeCards(window._entregasOrders);
      setupInteractions();
    }).catch(function (err) {
      alert('Erro: ' + (err.message || err));
    }).finally(function () {
      if (confirmBtn) confirmBtn.textContent = 'Confirmar remoção';
      updateToolbar(colId);
    });
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
      if (col) {
        var card = renderCard(order);
        if (colId && selectedByCol[colId] && selectedByCol[colId].has(String(order.id))) {
          card.classList.add('entregas-card--selected');
          var cb = card.querySelector('.entregas-card-checkbox');
          if (cb) cb.checked = true;
        }
        col.appendChild(card);
      }
    });
  }

  function setupEntregueSelection() {
    document.querySelectorAll('.entregas-card--selectable').forEach(function (card) {
      card.addEventListener('click', function (e) {
        var colId = getCardsColId(card);
        if (!removeModeByCol[colId]) return;
        e.preventDefault();
        toggleCardSelected(card);
      });
    });

    document.querySelectorAll('.entregas-start-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var colId = btn.getAttribute('data-start-remove-for');
        if (!colId) return;
        var total = document.querySelectorAll('#' + colId + ' .entregas-card--selectable').length;
        if (total === 0) {
          alert('Não há pedidos no histórico desta coluna.');
          return;
        }
        enterRemoveMode(colId);
      });
    });

    document.querySelectorAll('.entregas-cancel-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var colId = btn.getAttribute('data-cancel-remove-for');
        if (colId) exitRemoveMode(colId);
      });
    });

    document.querySelectorAll('.entregas-select-all').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var colId = btn.getAttribute('data-select-all-for');
        if (!colId) return;
        var total = document.querySelectorAll('#' + colId + ' .entregas-card--selectable').length;
        var n = (selectedByCol[colId] || new Set()).size;
        selectAllInCol(colId, n < total);
      });
    });

    document.querySelectorAll('.entregas-confirm-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var colId = btn.getAttribute('data-confirm-remove-for');
        if (colId) removeSelectedFromCol(colId);
      });
    });
  }

  function setupDragAndDrop() {
    document.querySelectorAll('.entregas-card[draggable="true"]').forEach(function (card) {
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

  function setupInteractions() {
    setupDragAndDrop();
    setupEntregueSelection();
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
      updated.delivery_stage = updated.delivery_stage || updated.deliveryStage || stage;
      updated.delivery_type = updated.delivery_type || updated.deliveryType || 'retirada';
      var idx = orders.findIndex(function (o) { return String(o.id) === String(orderId); });
      if (idx >= 0) {
        if (!updated.customer_name && orders[idx].customer_name) updated.customer_name = orders[idx].customer_name;
        orders[idx] = updated;
      } else {
        orders.push(updated);
      }
      if (String(stage).toLowerCase() === 'entregue') {
        ENTREGUE_COLS.forEach(function (colId) {
          selectedByCol[colId].delete(String(orderId));
          if (removeModeByCol[colId]) exitRemoveMode(colId);
        });
      }
      window._entregasOrders = orders;
      placeCards(orders);
      setupInteractions();
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
    setupInteractions();
  }).catch(function (err) {
    console.error(err);
    var msg = err && err.message ? err.message : 'Erro ao carregar.';
    var el = document.querySelector('.entregas-kanban');
    if (el) el.innerHTML = '<p class="text-muted">Erro ao carregar pedidos: ' + escapeHtml(msg) + '. Verifique a URL da página (deve ser pelo mesmo domínio do servidor, ex.: http://localhost/.../painel/...).</p>';
  });
})();
