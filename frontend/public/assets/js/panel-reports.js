(function () {
  if (typeof storeSlug === 'undefined') return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  var isGerente = typeof window.panelReadonly !== 'undefined' ? !window.panelReadonly : true;

  var WIDGET_LABELS = {
    revenue: 'Faturamento no período',
    top_products: 'Produtos mais vendidos',
    low_stock: 'Produtos com estoque baixo',
    employees: 'Desempenho de funcionários',
    goals: 'Metas (loja e por funcionário)'
  };

  var DEFAULT_WIDGETS = [
    { id: 'w1', type: 'revenue', title: '', width: 1, height: 1 },
    { id: 'w2', type: 'top_products', title: '', width: 1, height: 1 },
    { id: 'w3', type: 'low_stock', title: '', width: 1, height: 1 },
    { id: 'w4', type: 'employees', title: '', width: 1, height: 1 }
  ];

  var SIZE_OPTIONS = [
    { w: 1, h: 1, label: 'Normal (1×1)' },
    { w: 2, h: 1, label: 'Largo (2×1)' },
    { w: 1, h: 2, label: 'Alto (1×2)' },
    { w: 2, h: 2, label: 'Grande (2×2)' }
  ];

  var currentWidgets = [];
  var editMode = false;

  function api(path, opt) {
    var url = base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path);
    return fetch(url, { credentials: 'same-origin', ...opt }).then(function (r) {
      return r.text().then(function (text) {
        var j = null;
        try {
          j = text ? JSON.parse(text) : null;
        } catch (e) {
          j = null;
        }
        if (!r.ok) {
          var err = (j && j.error) ? j.error : (text || ('Erro HTTP ' + r.status));
          return Promise.reject(new Error(err));
        }
        return j !== null ? j : {};
      });
    });
  }

  function from() { return (document.getElementById('report-from') || {}).value || ''; }
  function to() { return (document.getElementById('report-to') || {}).value || ''; }

  /** Garante from <= to (datas YYYY-MM-DD do input type=date). */
  function fromToBounds() {
    var f = from();
    var t = to();
    if (!f || !t) return { from: f, to: t };
    if (f > t) {
      var x = f;
      f = t;
      t = x;
    }
    return { from: f, to: t };
  }
  function formatMoney(n) {
    return 'R$ ' + (n != null ? parseFloat(n).toFixed(2).replace('.', ',') : '0,00');
  }

  /** Percentual da meta (pt-BR): uma casa decimal com vírgula; não arredonda para 100% quando falta valor. */
  function formatGoalPercent(sales, goalVal) {
    var s = sales != null ? parseFloat(sales) : 0;
    var g = goalVal != null ? parseFloat(goalVal) : 0;
    if (!(g > 0)) {
      return s > 0 ? '100,0' : '0,0';
    }
    var pct = (s / g) * 100;
    return pct.toFixed(1).replace('.', ',');
  }

  function generateId() {
    return 'w' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
  }

  function normalizeWidget(w) {
    return {
      id: w.id,
      type: w.type,
      title: w.title || '',
      width: w.width === 2 ? 2 : 1,
      height: w.height === 2 ? 2 : 1
    };
  }

  function loadDashboardConfig() {
    api('/api/loja/' + storeSlug + '/dashboard-config').then(function (res) {
      var raw = (res.widgets && res.widgets.length) ? res.widgets : DEFAULT_WIDGETS.slice();
      currentWidgets = raw.map(normalizeWidget);
      renderDashboard();
    }).catch(function () {
      currentWidgets = DEFAULT_WIDGETS.map(normalizeWidget);
      renderDashboard();
    });
  }

  function getWidgetTitle(w) {
    return (w.title && w.title.trim()) ? w.title.trim() : (WIDGET_LABELS[w.type] || w.type);
  }

  function renderDashboard() {
    var container = document.getElementById('dashboard-widgets');
    var emptyEl = document.getElementById('dashboard-empty');
    if (!container) return;
    container.innerHTML = '';
    if (currentWidgets.length === 0) {
      if (emptyEl) emptyEl.classList.remove('hidden');
      return;
    }
    if (emptyEl) emptyEl.classList.add('hidden');
    currentWidgets.forEach(function (w, index) {
      var card = document.createElement('div');
      var cardClass = w.type === 'revenue' ? 'revenue' : w.type === 'top_products' ? 'products' : w.type === 'low_stock' ? 'stock' : w.type === 'goals' ? 'goals' : 'employees';
      var ww = w.width === 2 ? 2 : 1;
      var hh = w.height === 2 ? 2 : 1;
      card.className = 'dashboard-widget report-card report-card-' + cardClass + ' widget-size-' + ww + 'x' + hh;
      card.setAttribute('data-widget-id', w.id);
      card.setAttribute('data-widget-type', w.type);
      card.setAttribute('data-width', ww);
      card.setAttribute('data-height', hh);
      card.innerHTML = '<h2 class="dashboard-widget-title">' + (getWidgetTitle(w).replace(/</g, '&lt;')) + '</h2><div class="dashboard-widget-body" data-widget-id="' + w.id + '"></div>';
      if (editMode && isGerente) {
        var toolbar = document.createElement('div');
        toolbar.className = 'dashboard-widget-edit';
        var sizeSelect = '<select class="widget-size-select" data-widget-id="' + w.id + '" title="Tamanho">';
        SIZE_OPTIONS.forEach(function (opt) {
          var sel = (opt.w === ww && opt.h === hh) ? ' selected' : '';
          sizeSelect += '<option value="' + opt.w + ',' + opt.h + '"' + sel + '>' + opt.label + '</option>';
        });
        sizeSelect += '</select>';
        toolbar.innerHTML = sizeSelect + '<button type="button" class="btn-widget-move btn-widget-up" title="Subir">↑</button><button type="button" class="btn-widget-move btn-widget-down" title="Descer">↓</button><button type="button" class="btn-widget-remove" title="Remover">✕</button>';
        card.insertBefore(toolbar, card.firstChild);
      }
      container.appendChild(card);
    });
    currentWidgets.forEach(function (w) { loadWidgetData(w); });
    if (editMode) bindEditHandlers();
  }

  function loadWidgetData(w) {
    var body = document.querySelector('.dashboard-widget-body[data-widget-id="' + w.id + '"]');
    if (!body) return;
    var b = fromToBounds();
    var f = b.from;
    var t = b.to;
    var qDates = 'from=' + encodeURIComponent(f || '') + '&to=' + encodeURIComponent(t || '');
    if (w.type === 'revenue') {
      body.innerHTML = '<p class="report-empty">Carregando...</p>';
      api('/api/loja/' + storeSlug + '/reports/revenue?' + qDates).then(function (res) {
        var fisico = res.revenue_fisico != null ? res.revenue_fisico : 0;
        var online = res.revenue_online != null ? res.revenue_online : 0;
        var total = res.revenue != null ? res.revenue : (fisico + online);
        body.innerHTML = '<ul class="report-revenue-breakdown">' +
          '<li><span class="report-revenue-label">Físico:</span> <strong class="report-revenue-value">' + formatMoney(fisico) + '</strong> <small class="report-revenue-hint"></small></li>' +
          '<li><span class="report-revenue-label">Online:</span> <strong class="report-revenue-value">' + formatMoney(online) + '</strong> <small class="report-revenue-hint"></small></li>' +
          '<li class="report-revenue-total"><span class="report-revenue-label">Total Faturado:</span> <strong class="report-revenue-value">' + formatMoney(total) + '</strong> <small class="report-revenue-hint"></small></li>' +
          '</ul>';
      }).catch(function (err) {
        body.innerHTML = '<p class="report-empty report-error">' + String(err.message || err).replace(/</g, '&lt;') + '</p>';
      });
    } else if (w.type === 'top_products') {
      body.innerHTML = '<p class="report-empty">Carregando...</p>';
      api('/api/loja/' + storeSlug + '/reports/top-products?' + qDates).then(function (res) {
        var data = res.data || [];
        body.innerHTML = data.length ? '<ul class="report-list">' + data.map(function (p) {
          return '<li><span class="report-item-name">' + (p.name || '').replace(/</g, '&lt;') + '</span> — ' + (p.total_qty || 0) + ' un. — ' + formatMoney(p.revenue) + '</li>';
        }).join('') + '</ul>' : '<p class="report-empty">Nenhuma venda no período.</p>';
      }).catch(function (err) {
        body.innerHTML = '<p class="report-empty report-error">' + String(err.message || err).replace(/</g, '&lt;') + '</p>';
      });
    } else if (w.type === 'low_stock') {
      body.innerHTML = '<p class="report-empty">Carregando...</p>';
      api('/api/loja/' + storeSlug + '/reports/low-stock').then(function (res) {
        var data = res.data || [];
        body.innerHTML = data.length ? '<ul class="report-list">' + data.map(function (p) {
          return '<li><span class="report-item-name">' + (p.name || '').replace(/</g, '&lt;') + '</span> — Estoque: ' + (p.stock_quantity || 0) + ' (mín: ' + (p.min_stock || 0) + ')</li>';
        }).join('') + '</ul>' : '<p class="report-empty">Nenhum produto com estoque baixo.</p>';
      }).catch(function (err) {
        body.innerHTML = '<p class="report-empty report-error">' + String(err.message || err).replace(/</g, '&lt;') + '</p>';
      });
    } else if (w.type === 'employees') {
      body.innerHTML = '<p class="report-empty">Carregando...</p>';
      api('/api/loja/' + storeSlug + '/reports/employees?' + qDates).then(function (res) {
        var data = res.data || [];
        body.innerHTML = data.length ? '<ul class="report-list">' + data.map(function (e) {
          return '<li><span class="report-item-name">' + (e.name || '').replace(/</g, '&lt;') + '</span> — ' + (e.orders_count || 0) + ' pedidos — ' + formatMoney(e.total_sales) + '</li>';
        }).join('') + '</ul>' : '<p class="report-empty">Nenhum dado.</p>';
      }).catch(function (err) {
        body.innerHTML = '<p class="report-empty report-error">' + String(err.message || err).replace(/</g, '&lt;') + '</p>';
      });
    } else if (w.type === 'goals') {
      loadGoalsWidget(body);
    }
  }

  function goalsPeriodStorageKey() {
    return 'goalsPeriod_' + storeSlug;
  }

  /** Mês inicial do bloco metas: último escolhido (session) ou mês do filtro "De" ou mês atual. */
  function goalsPeriodDefault() {
    try {
      var s = sessionStorage.getItem(goalsPeriodStorageKey());
      if (s && /^\d{4}-\d{2}$/.test(s)) return s;
    } catch (e) {}
    var f = from() || '';
    return (f && f.length >= 7) ? f.slice(0, 7) : new Date().toISOString().slice(0, 7);
  }

  function loadGoalsWidget(body, periodOverride) {
    var period = periodOverride || (body.querySelector && body.querySelector('#goals-period-input') && body.querySelector('#goals-period-input').value) || goalsPeriodDefault();
    var b = fromToBounds();
    var q = 'period=' + encodeURIComponent(period) +
      '&from=' + encodeURIComponent(b.from || '') + '&to=' + encodeURIComponent(b.to || '');
    body.innerHTML = '<p class="report-empty">Carregando metas...</p>';
    api('/api/loja/' + storeSlug + '/goals?' + q).then(function (res) {
      try {
        if (res.period && /^\d{4}-\d{2}$/.test(res.period)) {
          sessionStorage.setItem(goalsPeriodStorageKey(), res.period);
        }
      } catch (e) {}
      var storeGoal = res.store_goal != null ? parseFloat(res.store_goal) : 0;
      var employees = res.employees || [];
      var canEdit = isGerente;
      var monthHuman = period ? period.replace(/-/, '/') : '';
      var rangeHint = (res.sales_from && res.sales_to)
        ? '<p class="goals-range-hint"><strong>Vendas</strong> e <strong>atingido %</strong> usam o mesmo período <strong>De / Até</strong> do topo (' + (res.sales_from || '').split('-').reverse().join('/') + ' a ' + (res.sales_to || '').split('-').reverse().join('/') + '). Os valores em <strong>Meta (R$)</strong> vêm do mês <strong>' + monthHuman + '</strong> (campo Período).</p>'
        : '';
      var form = canEdit ? '<div class="goals-form"><label>Período (mês)</label><input type="month" id="goals-period-input" value="' + period + '" class="goals-period"><br>' +
        '<label>Meta da loja (R$)</label><input type="number" step="0.01" min="0" id="goals-store-input" value="' + (storeGoal > 0 ? storeGoal : '') + '" placeholder="Ex: 50000" class="goals-store-amount">' +
        '<button type="button" class="btn btn-primary btn-sm goals-btn-set-store" data-period="' + period + '">Definir e distribuir entre funcionários</button></div>' : '';
      var table = '<div class="goals-table-wrap"><table class="goals-table"><thead><tr><th>Funcionário</th><th>Meta (R$)</th><th>Vendas no período</th><th>Atingido</th></tr></thead><tbody>';
      employees.forEach(function (emp) {
        var goalVal = emp.goal_amount != null ? parseFloat(emp.goal_amount) : 0;
        var sales = emp.total_sales != null ? parseFloat(emp.total_sales) : 0;
        var pctStr = formatGoalPercent(sales, goalVal);
        var goalCell = canEdit ? '<input type="number" step="0.01" min="0" class="goals-emp-input" data-user-id="' + emp.user_id + '" data-period="' + period + '" value="' + (goalVal > 0 ? goalVal : '') + '" placeholder="0">' : formatMoney(goalVal);
        table += '<tr><td>' + (emp.name || '').replace(/</g, '&lt;') + '</td><td>' + goalCell + '</td><td>' + formatMoney(sales) + '</td><td>' + pctStr + '%</td></tr>';
      });
      table += '</tbody></table></div>';
      body.innerHTML = '<div class="goals-widget-content">' + form + rangeHint + table + '</div>';
      if (canEdit) {
        body.querySelector('.goals-btn-set-store').addEventListener('click', function () {
          var periodVal = body.querySelector('#goals-period-input').value || body.querySelector('.goals-period').value || period;
          var storeVal = parseFloat((body.querySelector('#goals-store-input') || {}).value) || 0;
          if (storeVal <= 0) { alert('Informe a meta da loja em R$.'); return; }
          api('/api/loja/' + storeSlug + '/goals/store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ period: periodVal, goal_amount: storeVal, distribute_to_employees: true })
          }).then(function () {
            loadGoalsWidget(body, periodVal);
          }).catch(function () { alert('Erro ao salvar.'); });
        });
        body.querySelectorAll('.goals-emp-input').forEach(function (inp) {
          inp.addEventListener('blur', function () {
            var uid = inp.getAttribute('data-user-id');
            var p = inp.getAttribute('data-period') || period;
            var val = parseFloat(inp.value) || 0;
            api('/api/loja/' + storeSlug + '/goals/employee', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ user_id: parseInt(uid, 10), period: p, goal_amount: val })
            }).catch(function () {});
          });
        });
        var periodInput = body.querySelector('#goals-period-input');
        if (periodInput) {
          periodInput.addEventListener('change', function () {
            var v = periodInput.value;
            if (v && /^\d{4}-\d{2}$/.test(v)) {
              try { sessionStorage.setItem(goalsPeriodStorageKey(), v); } catch (e) {}
            }
            loadGoalsWidget(body, v);
          });
        }
      }
    }).catch(function (err) {
      body.innerHTML = '<p class="report-empty report-error">' + String((err && err.message) || err || 'Erro ao carregar metas.').replace(/</g, '&lt;') + '</p>';
    });
  }

  function bindEditHandlers() {
    document.querySelectorAll('.widget-size-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var id = sel.getAttribute('data-widget-id');
        var v = (sel.value || '1,1').split(',');
        var w = parseInt(v[0], 10) || 1;
        var h = parseInt(v[1], 10) || 1;
        var widget = currentWidgets.find(function (x) { return x.id === id; });
        if (widget) { widget.width = w; widget.height = h; renderDashboard(); }
      });
    });
    document.querySelectorAll('.btn-widget-remove').forEach(function (btn) {
      btn.onclick = function () {
        var card = btn.closest('.dashboard-widget');
        var id = card && card.getAttribute('data-widget-id');
        if (id) {
          currentWidgets = currentWidgets.filter(function (w) { return w.id !== id; });
          renderDashboard();
        }
      };
    });
    document.querySelectorAll('.btn-widget-up').forEach(function (btn) {
      btn.onclick = function () {
        var card = btn.closest('.dashboard-widget');
        var id = card && card.getAttribute('data-widget-id');
        if (!id) return;
        var i = currentWidgets.findIndex(function (w) { return w.id === id; });
        if (i > 0) {
          var tmp = currentWidgets[i]; currentWidgets[i] = currentWidgets[i - 1]; currentWidgets[i - 1] = tmp;
          renderDashboard();
        }
      };
    });
    document.querySelectorAll('.btn-widget-down').forEach(function (btn) {
      btn.onclick = function () {
        var card = btn.closest('.dashboard-widget');
        var id = card && card.getAttribute('data-widget-id');
        if (!id) return;
        var i = currentWidgets.findIndex(function (w) { return w.id === id; });
        if (i >= 0 && i < currentWidgets.length - 1) {
          var tmp = currentWidgets[i]; currentWidgets[i] = currentWidgets[i + 1]; currentWidgets[i + 1] = tmp;
          renderDashboard();
        }
      };
    });
  }

  function saveLayout() {
    api('/api/loja/' + storeSlug + '/dashboard-config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ widgets: currentWidgets })
    }).then(function () {
      alert('Layout salvo.');
      exitEditMode();
    }).catch(function () {
      alert('Erro ao salvar.');
    });
  }

  function exitEditMode() {
    editMode = false;
    var editBar = document.getElementById('dashboard-edit-bar');
    var btnEdit = document.getElementById('btn-edit-dashboard');
    if (editBar) editBar.classList.add('hidden');
    if (btnEdit) btnEdit.style.display = '';
    renderDashboard();
  }

  (function bindReportFilters() {
    function reload() {
      currentWidgets.forEach(loadWidgetData);
    }
    var elFrom = document.getElementById('report-from');
    var elTo = document.getElementById('report-to');
    if (elFrom) {
      elFrom.addEventListener('change', reload);
      elFrom.addEventListener('input', reload);
    }
    if (elTo) {
      elTo.addEventListener('change', reload);
      elTo.addEventListener('input', reload);
    }
  })();

  var btnEdit = document.getElementById('btn-edit-dashboard');
  if (btnEdit) {
    btnEdit.addEventListener('click', function () {
      editMode = true;
      btnEdit.style.display = 'none';
      var editBar = document.getElementById('dashboard-edit-bar');
      if (editBar) editBar.classList.remove('hidden');
      renderDashboard();
    });
  }

  var btnAdd = document.getElementById('btn-add-widget');
  var selectType = document.getElementById('widget-type');
  if (btnAdd && selectType) {
    btnAdd.addEventListener('click', function () {
      var type = (selectType.value || '').trim();
      if (!type) {
        alert('Escolha um tipo de bloco.');
        return;
      }
      currentWidgets.push({ id: generateId(), type: type, title: '', width: 1, height: 1 });
      selectType.value = '';
      renderDashboard();
    });
  }

  var btnSave = document.getElementById('btn-save-layout');
  if (btnSave) btnSave.addEventListener('click', saveLayout);

  var btnCancel = document.getElementById('btn-cancel-edit');
  if (btnCancel) btnCancel.addEventListener('click', exitEditMode);

  loadDashboardConfig();
})();
