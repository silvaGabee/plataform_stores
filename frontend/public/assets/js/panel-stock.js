(function () {
  if (typeof storeSlug === 'undefined') return;

  var readonly =
    typeof window.panelStockReadonly !== 'undefined'
      ? window.panelStockReadonly === true
      : typeof window.panelReadonly !== 'undefined' && window.panelReadonly;

  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  var allProducts = [];
  var activeAdjustMatrix = null;
  var searchInput = document.getElementById('stock-search');
  var filterSelect = document.getElementById('stock-filter');
  var adjustModal = document.getElementById('adjust-modal');
  var adjustModalContent = adjustModal ? adjustModal.querySelector('.modal-content--stock') : null;
  var variantTargetPanel = document.getElementById('adjust-variant-target');
  var variantColorSelect = document.getElementById('adjust-variant-color');
  var variantSizeSelect = document.getElementById('adjust-variant-size');

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function getProductDisplayName(p) {
    if (!p) return 'Produto';
    var n = String(p.display_name || p.name || p.product_name || '').trim();
    if (n) return n;
    return 'Produto #' + (p.id || '?');
  }

  function api(path, opt) {
    var url = base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path);
    return fetch(url, { headers: { 'Content-Type': 'application/json' }, ...(opt || {}) }).then(function (r) {
      return r.json();
    });
  }

  function getCoverImageFromProduct(p) {
    if (!p || !p.images || !p.images.length) return null;
    var cover = p.images.find(function (im) {
      return im.is_cover;
    });
    return cover || p.images[0];
  }

  function hasVariantMatrix(p) {
    var m = p && p.variants_matrix;
    return !!(
      m &&
      Array.isArray(m.colors) &&
      m.colors.length > 0 &&
      Array.isArray(m.sizes) &&
      m.sizes.length > 0 &&
      m.stock
    );
  }

  function matrixTotalStock(matrix) {
    if (!matrix || !matrix.stock) return 0;
    var total = 0;
    Object.keys(matrix.stock).forEach(function (size) {
      var row = matrix.stock[size];
      if (!row || typeof row !== 'object') return;
      Object.keys(row).forEach(function (color) {
        total += parseInt(row[color], 10) || 0;
      });
    });
    return total;
  }

  function effectiveStock(p) {
    if (hasVariantMatrix(p)) return matrixTotalStock(p.variants_matrix);
    return parseInt(p.stock_quantity, 10) || 0;
  }

  function findProduct(id) {
    var pid = parseInt(id, 10);
    return allProducts.find(function (p) {
      return parseInt(p.id, 10) === pid;
    });
  }

  function getMinStock(p) {
    return parseInt(p.min_stock, 10) || 0;
  }

  function lineStatus(qty, min) {
    if (qty <= 0) return { key: 'zero', label: 'Esgotado' };
    if (min > 0 && qty <= min) return { key: 'low', label: 'Estoque baixo' };
    return { key: 'ok', label: 'Em dia' };
  }

  function getVariantLines(p) {
    var lines = [];
    var min = getMinStock(p);
    if (hasVariantMatrix(p)) {
      var m = p.variants_matrix;
      (m.sizes || []).forEach(function (size) {
        (m.colors || []).forEach(function (color) {
          var qty = 0;
          if (m.stock && m.stock[size] && m.stock[size][color] != null) {
            qty = parseInt(m.stock[size][color], 10) || 0;
          }
          lines.push({
            label: color + ' · ' + size,
            color: color,
            size: size,
            qty: qty,
            min: min,
            status: lineStatus(qty, min)
          });
        });
      });
      return lines;
    }
    (p.variants || []).forEach(function (v) {
      if (!v || !v.variant_type || v.variant_type === '_meta') return;
      var type = String(v.variant_type);
      var value = String(v.variant_value || '');
      var qty = parseInt(v.stock_quantity, 10) || 0;
      if (type === 'combinacao' && value.indexOf('|') !== -1) {
        var parts = value.split('|', 2);
        lines.push({
          label: parts[0].trim() + ' · ' + parts[1].trim(),
          color: parts[0].trim(),
          size: parts[1].trim(),
          qty: qty,
          min: min,
          status: lineStatus(qty, min)
        });
      } else if (type !== 'combinacao') {
        lines.push({
          label: (v.variant_type_label || type) + ': ' + value,
          qty: qty,
          min: min,
          status: lineStatus(qty, min)
        });
      }
    });
    return lines;
  }

  function hasVariantBreakdown(p) {
    return getVariantLines(p).length > 0;
  }

  function stockStatus(p) {
    var lines = getVariantLines(p);
    if (lines.length) {
      return productAggregateStatus(p);
    }
    var qty = effectiveStock(p);
    return lineStatus(qty, getMinStock(p));
  }

  function productAggregateStatus(p) {
    var lines = getVariantLines(p);
    if (!lines.length) {
      return lineStatus(effectiveStock(p), getMinStock(p));
    }
    var allZero = true;
    var anyZero = false;
    var anyLow = false;
    lines.forEach(function (ln) {
      if (ln.qty > 0) allZero = false;
      if (ln.status.key === 'zero') anyZero = true;
      if (ln.status.key === 'low') anyLow = true;
    });
    if (allZero) return { key: 'zero', label: 'Esgotado' };
    if (anyZero) return { key: 'zero', label: 'Com esgotados' };
    if (anyLow) return { key: 'low', label: 'Estoque baixo' };
    return { key: 'ok', label: 'Em dia' };
  }

  function productHasAlert(p) {
    var lines = getVariantLines(p);
    if (lines.length) {
      return lines.some(function (ln) {
        return ln.status.key === 'low' || ln.status.key === 'zero';
      });
    }
    var st = lineStatus(effectiveStock(p), getMinStock(p));
    return st.key === 'low' || st.key === 'zero';
  }

  function meterPercent(p) {
    var lines = getVariantLines(p);
    var min = getMinStock(p);
    if (lines.length) {
      if (lines.every(function (ln) {
        return ln.qty <= 0;
      })) {
        return 0;
      }
      if (min > 0) {
        var worst = 100;
        lines.forEach(function (ln) {
          if (ln.qty <= 0) worst = 0;
          else worst = Math.min(worst, Math.round((ln.qty / min) * 100));
        });
        return Math.min(100, worst);
      }
      var maxQty = 0;
      lines.forEach(function (ln) {
        if (ln.qty > maxQty) maxQty = ln.qty;
      });
      return Math.min(100, Math.round((maxQty / Math.max(maxQty, 50)) * 100));
    }
    var qty = effectiveStock(p);
    if (qty <= 0) return 0;
    if (min > 0) return Math.min(100, Math.round((qty / min) * 100));
    return Math.min(100, Math.round((qty / Math.max(qty, 50)) * 100));
  }

  function collectAlerts(products) {
    var alerts = [];
    products.forEach(function (p) {
      var min = getMinStock(p);
      var lines = getVariantLines(p);
      if (lines.length) {
        lines.forEach(function (ln) {
          if (ln.status.key !== 'low' && ln.status.key !== 'zero') return;
          alerts.push({
            productId: p.id,
            productName: getProductDisplayName(p),
            label: ln.label,
            qty: ln.qty,
            min: min,
            status: ln.status
          });
        });
        return;
      }
      var st = lineStatus(effectiveStock(p), min);
      if (st.key === 'low' || st.key === 'zero') {
        alerts.push({
          productId: p.id,
          productName: getProductDisplayName(p),
          label: '',
          qty: effectiveStock(p),
          min: min,
          status: st
        });
      }
    });
    alerts.sort(function (a, b) {
      if (a.status.key !== b.status.key) {
        if (a.status.key === 'zero') return -1;
        if (b.status.key === 'zero') return 1;
      }
      return String(a.productName).localeCompare(String(b.productName), 'pt-BR');
    });
    return alerts;
  }

  function renderAlerts(products) {
    var box = document.getElementById('stock-alerts');
    var list = document.getElementById('stock-alerts-list');
    if (!box || !list) return;
    var alerts = collectAlerts(products);
    if (!alerts.length) {
      box.classList.add('hidden');
      list.innerHTML = '';
      return;
    }
    box.classList.remove('hidden');
    list.innerHTML = alerts
      .map(function (a) {
        var detail =
          a.label !== ''
            ? '<strong>' + esc(a.label) + '</strong>'
            : '<span class="panel-stock-alert-product-only">Produto inteiro</span>';
        var qtyPart =
          a.status.key === 'zero'
            ? 'sem unidades'
            : a.qty + ' un.' + (a.min > 0 ? ' (mín. ' + a.min + ')' : '');
        return (
          '<li class="panel-stock-alert-item panel-stock-alert-item--' +
          esc(a.status.key) +
          '">' +
          '<span class="panel-stock-alert-product">' +
          esc(a.productName) +
          '</span> — ' +
          detail +
          ': <span class="panel-stock-alert-qty">' +
          esc(qtyPart) +
          '</span>' +
          '<span class="panel-stock-badge panel-stock-badge--' +
          esc(a.status.key) +
          '">' +
          esc(a.status.label) +
          '</span>' +
          '</li>'
        );
      })
      .join('');
  }

  function normalizeSearch(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function matchesFilters(p) {
    var q = searchInput ? normalizeSearch(searchInput.value).trim() : '';
    if (q && normalizeSearch(getProductDisplayName(p)).indexOf(q) === -1) return false;
    var f = filterSelect ? filterSelect.value : 'all';
    if (f === 'all') return true;
    if (f === 'alerts') return productHasAlert(p);
    var lines = getVariantLines(p);
    if (lines.length) {
      if (f === 'low') {
        return lines.some(function (ln) {
          return ln.status.key === 'low';
        });
      }
      if (f === 'zero') {
        return lines.some(function (ln) {
          return ln.status.key === 'zero';
        });
      }
      if (f === 'ok') {
        return lines.every(function (ln) {
          return ln.status.key === 'ok';
        });
      }
    }
    return stockStatus(p).key === f;
  }

  function updateStats(list) {
    var total = list.length;
    var low = 0;
    var zero = 0;
    var units = 0;
    list.forEach(function (p) {
      var lines = getVariantLines(p);
      if (lines.length) {
        lines.forEach(function (ln) {
          if (ln.status.key === 'low') low++;
          if (ln.status.key === 'zero') zero++;
        });
      } else {
        var st = lineStatus(effectiveStock(p), getMinStock(p));
        if (st.key === 'low') low++;
        if (st.key === 'zero') zero++;
      }
      units += effectiveStock(p);
    });
    var elTotal = document.getElementById('stock-stat-total');
    var elLow = document.getElementById('stock-stat-low');
    var elZero = document.getElementById('stock-stat-zero');
    var elUnits = document.getElementById('stock-stat-units');
    if (elTotal) elTotal.textContent = String(total);
    if (elLow) elLow.textContent = String(low);
    if (elZero) elZero.textContent = String(zero);
    if (elUnits) elUnits.textContent = String(units);
    var legacy = document.getElementById('low-stock-count');
    if (legacy) legacy.textContent = String(low);
  }

  function renderThumb(p) {
    var coverIm = getCoverImageFromProduct(p);
    if (coverIm && coverIm.url) {
      return (
        '<span class="panel-stock-thumb"><img src="' +
        esc(coverIm.url) +
        '" alt="" width="48" height="48" decoding="async" loading="lazy"></span>'
      );
    }
    return (
      '<span class="panel-stock-thumb panel-stock-thumb--empty" aria-hidden="true">' +
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" stroke="currentColor" stroke-width="1.25" opacity="0.45"/></svg></span>'
    );
  }

  function sortVariantLines(lines) {
    var order = { zero: 0, low: 1, ok: 2 };
    return lines.slice().sort(function (a, b) {
      var da = order[a.status.key] != null ? order[a.status.key] : 9;
      var db = order[b.status.key] != null ? order[b.status.key] : 9;
      if (da !== db) return da - db;
      return String(a.label).localeCompare(String(b.label), 'pt-BR');
    });
  }

  function renderVariantBreakdown(p) {
    var lines = getVariantLines(p);
    if (!lines.length) return '';
    var min = getMinStock(p);
    var minHint =
      min > 0
        ? 'Mínimo por combinação: <strong>' + min + '</strong> un.'
        : 'Defina um estoque mínimo no produto para alertas automáticos.';
    var chips = sortVariantLines(lines)
      .map(function (ln) {
        var dataSel =
          ln.color && ln.size
            ? ' data-color="' + esc(ln.color) + '" data-size="' + esc(ln.size) + '"'
            : '';
        return (
          '<div class="panel-stock-variant-chip panel-stock-variant-chip--' +
          esc(ln.status.key) +
          '"' +
          dataSel +
          ' title="' +
          esc(ln.label) +
          ' — ' +
          ln.qty +
          ' un.">' +
          '<span class="panel-stock-variant-chip-label">' +
          esc(ln.label) +
          '</span>' +
          '<span class="panel-stock-variant-chip-qty">' +
          ln.qty +
          '<span class="panel-stock-variant-chip-unit">un.</span></span>' +
          '<span class="panel-stock-badge panel-stock-badge--' +
          esc(ln.status.key) +
          '">' +
          esc(ln.status.label) +
          '</span>' +
          '</div>'
        );
      })
      .join('');
    return (
      '<div class="panel-stock-variants-panel">' +
      '<p class="panel-stock-variants-title">Estoque por cor e tamanho <span class="panel-stock-variants-min">' +
      minHint +
      '</span></p>' +
      '<div class="panel-stock-variants-grid">' +
      chips +
      '</div></div>'
    );
  }

  function renderRow(p) {
    var st = stockStatus(p);
    var pct = meterPercent(p);
    var qty = effectiveStock(p);
    var min = getMinStock(p);
    var lines = getVariantLines(p);
    var hasRealName = !!(String(p.name || '').trim());
    var variantNote = hasVariantBreakdown(p)
      ? '<span class="panel-stock-variant-tag">' +
        lines.length +
        ' combinações · cor · ' +
        esc(
          hasVariantMatrix(p)
            ? (p.variants_matrix.axis_label || 'tamanho').toLowerCase()
            : 'variação'
        ) +
        '</span>'
      : '';
    var unnamedNote = !hasRealName
      ? '<span class="panel-stock-unnamed-tag">Sem nome no cadastro — edite em Produtos</span>'
      : '';
    var adj = readonly
      ? ''
      : '<button type="button" class="btn btn-primary btn-sm btn-adjust" data-id="' +
        p.id +
        '" data-name="' +
        esc(getProductDisplayName(p)) +
        '">Ajustar</button>';
  return (
      '<article class="panel-stock-item card" data-status="' +
      st.key +
      '">' +
      '<div class="panel-stock-row">' +
      '<div class="panel-stock-col panel-stock-col--product">' +
      '<div class="panel-stock-row-main">' +
      renderThumb(p) +
      '<div class="panel-stock-row-text"><strong class="panel-stock-row-name">' +
      esc(getProductDisplayName(p)) +
      '</strong>' +
      variantNote +
      unnamedNote +
      '</div></div></div>' +
      '<div class="panel-stock-col panel-stock-col--qty" data-label="Quantidade">' +
      '<span class="panel-stock-qty-value">' +
      qty +
      '</span><span class="panel-stock-qty-unit">un. total</span></div>' +
      '<div class="panel-stock-col panel-stock-col--min" data-label="Mínimo">' +
      '<span class="panel-stock-min-value">' +
      min +
      '</span></div>' +
      '<div class="panel-stock-col panel-stock-col--level" data-label="Nível">' +
      '<div class="panel-stock-meter" role="presentation"><div class="panel-stock-meter-fill panel-stock-meter-fill--' +
      st.key +
      '" style="width:' +
      pct +
      '%"></div></div></div>' +
      '<div class="panel-stock-col panel-stock-col--status" data-label="Status">' +
      '<span class="panel-stock-badge panel-stock-badge--' +
      st.key +
      '">' +
      esc(st.label) +
      '</span></div>' +
      '<div class="panel-stock-col panel-stock-col--action">' +
      adj +
      '</div></div>' +
      renderVariantBreakdown(p) +
      '</article>'
    );
  }

  function renderList() {
    var listEl = document.getElementById('stock-list');
    if (!listEl) return;
    updateStats(allProducts);
    renderAlerts(allProducts);
    var filtered = allProducts.filter(matchesFilters);
    if (!allProducts.length) {
      listEl.innerHTML =
        '<div class="panel-stock-empty"><p>Nenhum produto cadastrado.</p><p class="text-muted">Cadastre produtos em <strong>Produtos</strong> para gerenciar o estoque aqui.</p></div>';
      return;
    }
    if (!filtered.length) {
      listEl.innerHTML =
        '<div class="panel-stock-empty"><p>Nenhum produto encontrado.</p><p class="text-muted">Tente outro termo ou filtro.</p></div>';
      return;
    }
    listEl.innerHTML = filtered.map(renderRow).join('');
    listEl.querySelectorAll('.btn-adjust').forEach(function (b) {
      b.addEventListener('click', function () {
        openAdjustModal(this.dataset.id, this.dataset.name || '');
      });
    });
    if (!readonly) {
      listEl.querySelectorAll('.panel-stock-variant-chip[data-color][data-size]').forEach(function (chip) {
        chip.classList.add('is-clickable');
        chip.setAttribute('role', 'button');
        chip.setAttribute('tabindex', '0');
        chip.addEventListener('click', function () {
          var item = chip.closest('.panel-stock-item');
          var btn = item ? item.querySelector('.btn-adjust') : null;
          if (!btn) return;
          openAdjustModal(btn.dataset.id, btn.dataset.name || '', chip.dataset.color, chip.dataset.size);
        });
        chip.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            chip.click();
          }
        });
      });
    }
  }

  function setAdjustModalMode(useVariants) {
    if (variantTargetPanel) variantTargetPanel.classList.toggle('hidden', !useVariants);
    updateAdjustTypeUi(useVariants);
  }

  function getCellStock(color, size) {
    if (!activeAdjustMatrix || !color || !size) return 0;
    if (!activeAdjustMatrix.stock[size]) return 0;
    return parseInt(activeAdjustMatrix.stock[size][color], 10) || 0;
  }

  function populateVariantTargetSelects() {
    if (!activeAdjustMatrix || !variantColorSelect || !variantSizeSelect) return;
    var colors = activeAdjustMatrix.colors || [];
    var sizes = activeAdjustMatrix.sizes || [];
    variantColorSelect.innerHTML = colors
      .map(function (c) {
        return '<option value="' + esc(c) + '">' + esc(c) + '</option>';
      })
      .join('');
    variantSizeSelect.innerHTML = sizes
      .map(function (s) {
        return '<option value="' + esc(s) + '">' + esc(s) + '</option>';
      })
      .join('');
    var sizeLabel = document.getElementById('adjust-variant-size-label');
    if (sizeLabel) {
      sizeLabel.textContent =
        activeAdjustMatrix.axis === 'numeracao' ? 'Numeração' : 'Tamanho';
    }
    updateVariantPreview();
  }

  function updateVariantPreview() {
    var preview = document.getElementById('adjust-variant-preview');
    if (!preview || !activeAdjustMatrix) return;
    var color = variantColorSelect ? variantColorSelect.value : '';
    var size = variantSizeSelect ? variantSizeSelect.value : '';
    var qty = parseInt(document.getElementById('adjust-qty').value, 10) || 0;
    var type = getAdjustType();
    if (!color || !size || qty < 1) {
      preview.textContent = '';
      return;
    }
    var cur = getCellStock(color, size);
    var next = cur;
    if (type === 'entrada') next = cur + qty;
    else if (type === 'saida') next = Math.max(0, cur - qty);
    else next = qty;
    if (type === 'saida' && cur < qty) {
      preview.textContent = 'Estoque atual: ' + cur + ' un. — insuficiente para saída de ' + qty + ' un.';
      preview.classList.add('is-error');
      return;
    }
    preview.classList.remove('is-error');
    var action =
      type === 'entrada' ? 'após entrada' : type === 'saida' ? 'após saída' : 'após ajuste';
    preview.textContent =
      esc(color) + ' · ' + esc(size) + ': ' + cur + ' un. → ' + next + ' un. (' + action + ')';
  }

  function applyVariantMovement(type, qty, color, size) {
    if (!activeAdjustMatrix || !color || !size) return false;
    if (!activeAdjustMatrix.stock[size]) activeAdjustMatrix.stock[size] = {};
    var cur = getCellStock(color, size);
    if (type === 'entrada') {
      activeAdjustMatrix.stock[size][color] = cur + qty;
      return true;
    }
    if (type === 'saida') {
      if (cur < qty) {
        alert('Estoque insuficiente em ' + color + ' · ' + size + '. Atual: ' + cur + ' un.');
        return false;
      }
      activeAdjustMatrix.stock[size][color] = cur - qty;
      return true;
    }
    activeAdjustMatrix.stock[size][color] = Math.max(0, qty);
    return true;
  }

  function updateVariantTotalUi() {
    var el = document.getElementById('adjust-variant-total');
    if (el) el.textContent = String(matrixTotalStock(activeAdjustMatrix));
  }

  function openAdjustModal(id, name, presetColor, presetSize) {
    var product = findProduct(id);
    document.getElementById('adjust-product-id').value = id;
    document.getElementById('adjust-product-name').textContent = name;
    activeAdjustMatrix = null;

    var qtyEl = document.getElementById('adjust-qty');
    if (qtyEl) qtyEl.value = '';
    var reasonEl = document.getElementById('adjust-reason');
    if (reasonEl) reasonEl.value = '';
    var entrada = document.querySelector('input[name="adjust-type"][value="entrada"]');
    if (entrada) entrada.checked = true;

    if (product && hasVariantMatrix(product)) {
      activeAdjustMatrix = JSON.parse(JSON.stringify(product.variants_matrix));
      setAdjustModalMode(true);
      populateVariantTargetSelects();
      if (presetColor && variantColorSelect) {
        variantColorSelect.value = presetColor;
      }
      if (presetSize && variantSizeSelect) {
        variantSizeSelect.value = presetSize;
      }
      updateVariantTotalUi();
      updateVariantPreview();
    } else {
      activeAdjustMatrix = null;
      setAdjustModalMode(false);
      updateAdjustTypeUi(false);
    }

    if (adjustModal) adjustModal.classList.remove('hidden');
    if (qtyEl) qtyEl.focus();
  }

  function closeAdjustModal() {
    activeAdjustMatrix = null;
    if (adjustModal) adjustModal.classList.add('hidden');
    setAdjustModalMode(false);
  }

  function load() {
    var listEl = document.getElementById('stock-list');
    if (listEl) {
      listEl.innerHTML = '<div class="panel-stock-loading">Carregando estoque…</div>';
    }
    api('/api/loja/' + storeSlug + '/products')
      .then(function (res) {
        allProducts = res.products || [];
        renderList();
      })
      .catch(function () {
        if (listEl) {
          listEl.innerHTML =
            '<div class="panel-stock-empty"><p>Não foi possível carregar o estoque.</p><p class="text-muted">Atualize a página e tente novamente.</p></div>';
        }
      });
  }

  if (searchInput) searchInput.addEventListener('input', renderList);
  if (filterSelect) filterSelect.addEventListener('change', renderList);

  if (adjustModal) {
    adjustModal.querySelectorAll('.close-modal').forEach(function (b) {
      b.addEventListener('click', closeAdjustModal);
    });
  }

  function getAdjustType() {
    var checked = document.querySelector('input[name="adjust-type"]:checked');
    return checked ? checked.value : 'entrada';
  }

  function updateAdjustTypeUi(isVariant) {
    var type = getAdjustType();
    var hint = document.getElementById('adjust-type-hint');
    var qtyLabel = document.getElementById('adjust-qty-label');
    var qtyInput = document.getElementById('adjust-qty');
    if (hint) {
      if (isVariant) {
        if (type === 'entrada') {
          hint.textContent =
            'Soma a quantidade ao estoque da cor e do tamanho selecionados.';
        } else if (type === 'saida') {
          hint.textContent =
            'Subtrai a quantidade do estoque da combinação selecionada.';
        } else {
          hint.textContent =
            'Define o estoque exato da combinação selecionada (substitui o valor atual).';
        }
      } else if (type === 'entrada') {
        hint.textContent = 'Adiciona unidades ao estoque atual do produto.';
      } else if (type === 'saida') {
        hint.textContent = 'Remove unidades do estoque atual do produto.';
      } else {
        hint.textContent = 'Define o estoque final do produto com o valor informado.';
      }
    }
    if (qtyLabel) {
      qtyLabel.textContent = type === 'ajuste' ? 'Novo estoque' : 'Quantidade';
    }
    if (qtyInput) {
      qtyInput.placeholder = type === 'ajuste' ? 'Ex.: 25' : 'Ex.: 10';
    }
    updateVariantPreview();
  }

  document.querySelectorAll('input[name="adjust-type"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      updateAdjustTypeUi(!!activeAdjustMatrix);
    });
  });
  if (variantColorSelect) variantColorSelect.addEventListener('change', updateVariantPreview);
  if (variantSizeSelect) variantSizeSelect.addEventListener('change', updateVariantPreview);
  var adjustQtyEl = document.getElementById('adjust-qty');
  if (adjustQtyEl) {
    adjustQtyEl.addEventListener('input', function () {
      updateVariantPreview();
    });
  }

  var adjustForm = document.getElementById('adjust-form');
  if (adjustForm) {
    adjustForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('adjust-product-id').value;
      if (activeAdjustMatrix) {
        var qty = parseInt(document.getElementById('adjust-qty').value, 10);
        if (!qty || qty < 1) {
          alert('Informe uma quantidade válida.');
          return;
        }
        var color = variantColorSelect ? variantColorSelect.value : '';
        var size = variantSizeSelect ? variantSizeSelect.value : '';
        if (!color || !size) {
          alert('Selecione a cor e o tamanho (ou numeração).');
          return;
        }
        var matrixCopy = JSON.parse(JSON.stringify(activeAdjustMatrix));
        var prevMatrix = activeAdjustMatrix;
        activeAdjustMatrix = matrixCopy;
        if (!applyVariantMovement(getAdjustType(), qty, color, size)) {
          activeAdjustMatrix = prevMatrix;
          return;
        }
        var payload = {
          variants_matrix: {
            axis: activeAdjustMatrix.axis,
            colors: activeAdjustMatrix.colors,
            sizes: activeAdjustMatrix.sizes,
            stock: activeAdjustMatrix.stock,
            color_meta: activeAdjustMatrix.color_meta || {}
          }
        };
        api('/api/loja/' + storeSlug + '/products/' + id, {
          method: 'PUT',
          body: JSON.stringify(payload)
        }).then(function (res) {
          if (res.error) {
            alert(res.error);
            activeAdjustMatrix = prevMatrix;
            return;
          }
          closeAdjustModal();
          load();
        });
        return;
      }
      var qty = parseInt(document.getElementById('adjust-qty').value, 10);
      if (!qty || qty < 1) {
        alert('Informe uma quantidade válida.');
        return;
      }
      api('/api/loja/' + storeSlug + '/products/' + id + '/stock', {
        method: 'POST',
        body: JSON.stringify({
          type: getAdjustType(),
          quantity: qty,
          reason: document.getElementById('adjust-reason').value
        })
      }).then(function (res) {
        if (res.error) {
          alert(res.error);
          return;
        }
        closeAdjustModal();
        load();
      });
    });
  }

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
      })
        .then(function (r) {
          return r.text().then(function (text) {
            var data = null;
            try {
              data = text ? JSON.parse(text) : {};
            } catch (e) {}
            if (!r.ok) {
              var errMsg = data && data.error ? data.error : text || 'Erro ' + r.status;
              throw new Error(errMsg);
            }
            return data;
          });
        })
        .then(function (res) {
          if (res && res.error) {
            alert(res.error);
            return;
          }
          closeAdjustModal();
          load();
        })
        .catch(function (err) {
          alert('Erro ao excluir: ' + (err.message || err));
        });
    });
  }

  load();
})();
