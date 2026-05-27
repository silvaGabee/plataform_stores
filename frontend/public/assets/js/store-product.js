(function () {
  var MOBILE_MAX = 899;

  function isMobileGallery() {
    return window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches;
  }

  function initProductGallery() {
    var grid = document.getElementById('product-gallery-grid');
    var ui = document.getElementById('product-gallery-mobile-ui');
    var dotsCont = document.getElementById('product-gallery-dots');
    var counter = document.getElementById('product-gallery-counter');
    if (!grid || !dotsCont) return;

    var slides = grid.querySelectorAll('.product-gallery-cell');
    var total = slides.length;
    if (total <= 1) return;

    dotsCont.innerHTML = '';
    var dots = [];
    for (var i = 0; i < total; i++) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'product-gallery-dot' + (i === 0 ? ' is-active' : '');
      dot.setAttribute('role', 'tab');
      dot.setAttribute('aria-label', 'Foto ' + (i + 1));
      dot.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
      (function (idx) {
        dot.addEventListener('click', function () {
          goToSlide(idx, true);
        });
      })(i);
      dotsCont.appendChild(dot);
      dots.push(dot);
    }

    function updateCounter(idx) {
      if (counter) counter.textContent = (idx + 1) + ' / ' + total;
    }

    function setActiveDot(idx) {
      dots.forEach(function (d, k) {
        d.classList.toggle('is-active', k === idx);
        d.setAttribute('aria-selected', k === idx ? 'true' : 'false');
      });
      updateCounter(idx);
    }

    function currentIndex() {
      var w = grid.clientWidth;
      if (w <= 0) return 0;
      return Math.round(grid.scrollLeft / w);
    }

    function goToSlide(idx, smooth) {
      var w = grid.clientWidth;
      if (w <= 0) return;
      grid.scrollTo({ left: idx * w, behavior: smooth ? 'smooth' : 'auto' });
      setActiveDot(idx);
    }

    function onScroll() {
      if (!isMobileGallery()) return;
      var idx = currentIndex();
      if (idx < 0) idx = 0;
      if (idx >= total) idx = total - 1;
      setActiveDot(idx);
    }

    function syncUiVisibility() {
      var mobile = isMobileGallery();
      if (ui) {
        ui.setAttribute('aria-hidden', mobile ? 'false' : 'true');
        ui.style.display = mobile ? '' : 'none';
      }
      if (mobile) onScroll();
    }

    grid.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', syncUiVisibility);
    syncUiVisibility();
    updateCounter(0);
  }

  initProductGallery();

  var root = document.querySelector('.product-detail--v2');
  if (!root) return;

  var stockLabel = document.getElementById('product-stock-label');
  var qtyInput = document.getElementById('qty');
  var qtyMinus = document.getElementById('qty-minus');
  var qtyPlus = document.getElementById('qty-plus');
  var addBtn = document.getElementById('product-add-cart');
  var stockTypePriority = ['tamanho', 'numeracao', 'cor'];

  function syncAddBtnVariantKey(variantKey) {
    if (!addBtn) return;
    var hasVariants = root.getAttribute('data-has-variants') === '1';
    if (!hasVariants) {
      addBtn.dataset.requireVariant = '0';
      delete addBtn.dataset.variantKey;
      return;
    }
    addBtn.dataset.requireVariant = '1';
    var vk = variantKey ? String(variantKey).trim() : '';
    if (vk) {
      addBtn.dataset.variantKey = vk;
    } else {
      delete addBtn.dataset.variantKey;
    }
  }

  function buildGroupedVariantKey(selectedMap) {
    var i;
    for (i = 0; i < stockTypePriority.length; i++) {
      var t = stockTypePriority[i];
      if (selectedMap[t]) {
        return t + ':' + selectedMap[t].value;
      }
    }
    var keys = Object.keys(selectedMap);
    if (!keys.length) return '';
    var t0 = keys[0];
    return t0 + ':' + selectedMap[t0].value;
  }

  function wireQty(stock) {
    var ready = stock > 0;
    if (qtyInput) {
      qtyInput.disabled = !ready;
      qtyInput.max = ready ? stock : 1;
      if (!ready) qtyInput.value = '1';
      else {
        var v = parseInt(qtyInput.value, 10) || 1;
        if (v > stock) qtyInput.value = String(stock);
        if (v < 1) qtyInput.value = '1';
      }
    }
    if (addBtn) {
      addBtn.disabled = !ready;
      addBtn.dataset.max = ready ? String(stock) : '0';
    }
  }

  if (root.getAttribute('data-has-matrix') === '1') {
    var matrixEl = document.getElementById('product-variants-matrix-json');
    var matrix = null;
    try {
      matrix = matrixEl ? JSON.parse(matrixEl.textContent) : null;
    } catch (e) {
      matrix = null;
    }
    var selectors = document.getElementById('product-variant-selectors');
    var sizeFieldset = document.getElementById('product-variant-size-fieldset');
    var sizeOptions = document.getElementById('product-variant-size-options');
    var selectedColor = null;
    var selectedSize = null;

    function stockFor(color, size) {
      if (!matrix || !matrix.stock || !matrix.stock[size]) return 0;
      return parseInt(matrix.stock[size][color], 10) || 0;
    }

    function colorHasStock(color) {
      if (!matrix || !matrix.sizes) return false;
      for (var i = 0; i < matrix.sizes.length; i++) {
        if (stockFor(color, matrix.sizes[i]) > 0) return true;
      }
      return false;
    }

    function updateStockLabel() {
      if (!stockLabel) return;
      if (!selectedColor) {
        stockLabel.textContent = 'Selecione a cor';
        syncAddBtnVariantKey('');
        wireQty(0);
        return;
      }
      if (!selectedSize) {
        stockLabel.textContent = 'Selecione o ' + (matrix.axis_label || 'tamanho').toLowerCase();
        syncAddBtnVariantKey('');
        wireQty(0);
        return;
      }
      var stock = stockFor(selectedColor, selectedSize);
      syncAddBtnVariantKey(selectedColor + '|' + selectedSize);
      if (stock <= 0) {
        stockLabel.textContent = 'Combinação indisponível';
        wireQty(0);
      } else {
        stockLabel.textContent = stock === 1 ? '1 unidade disponível' : stock + ' unidades disponíveis';
        wireQty(stock);
      }
    }

    function renderSizesForColor(color) {
      if (!sizeOptions || !matrix) return;
      sizeOptions.innerHTML = '';
      (matrix.sizes || []).forEach(function (size) {
        var stock = stockFor(color, size);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'product-variant-option' + (stock <= 0 ? ' is-disabled' : '');
        btn.textContent = size;
        btn.setAttribute('data-variant-value', size);
        if (stock <= 0) {
          btn.disabled = true;
        } else {
          btn.addEventListener('click', function () {
            if (btn.disabled) return;
            sizeOptions.querySelectorAll('.product-variant-option').forEach(function (b) {
              b.classList.remove('is-selected');
            });
            btn.classList.add('is-selected');
            selectedSize = size;
            updateStockLabel();
          });
        }
        if (selectedSize === size && stock > 0) btn.classList.add('is-selected');
        sizeOptions.appendChild(btn);
      });
    }

    if (selectors) {
      selectors.querySelectorAll('.product-variant-color').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (btn.disabled) return;
          var color = btn.getAttribute('data-variant-value');
          selectors.querySelectorAll('.product-variant-color').forEach(function (b) {
            b.classList.remove('is-selected');
          });
          btn.classList.add('is-selected');
          selectedColor = color;
          selectedSize = null;
          if (sizeFieldset) {
            sizeFieldset.classList.remove('hidden');
            sizeFieldset.setAttribute('aria-hidden', 'false');
          }
          renderSizesForColor(color);
          updateStockLabel();
        });
      });
    }

    if (qtyMinus && qtyInput) {
      qtyMinus.addEventListener('click', function () {
        var v = (parseInt(qtyInput.value, 10) || 1) - 1;
        qtyInput.value = String(Math.max(1, v));
      });
    }
    if (qtyPlus && qtyInput) {
      qtyPlus.addEventListener('click', function () {
        var max = parseInt(qtyInput.max, 10) || 1;
        var v = (parseInt(qtyInput.value, 10) || 1) + 1;
        qtyInput.value = String(Math.min(max, v));
      });
    }

    updateStockLabel();
    return;
  }

  if (root.getAttribute('data-has-variants') !== '1') return;

  var selectors = document.getElementById('product-variant-selectors');
  if (!selectors || !stockLabel) return;

  var selected = {};
  var fieldsets = selectors.querySelectorAll('.product-variant-fieldset');

  function allGroupsSelected() {
    for (var i = 0; i < fieldsets.length; i++) {
      var type = fieldsets[i].querySelector('.product-variant-option, .product-variant-color');
      if (!type) continue;
      var t = type.getAttribute('data-variant-type');
      if (!t || !selected[t]) return false;
    }
    return fieldsets.length > 0;
  }

  function getSelectedStock() {
    if (!allGroupsSelected()) return 0;
    var i;
    for (i = 0; i < stockTypePriority.length; i++) {
      if (selected[stockTypePriority[i]]) {
        return selected[stockTypePriority[i]].stock;
      }
    }
    var keys = Object.keys(selected);
    return keys.length ? selected[keys[0]].stock : 0;
  }

  function updateUi() {
    var stock = getSelectedStock();
    var ready = allGroupsSelected() && stock > 0;

    if (!allGroupsSelected()) {
      stockLabel.textContent = 'Selecione todas as opções para ver o estoque';
      syncAddBtnVariantKey('');
    } else if (stock <= 0) {
      stockLabel.textContent = 'Opção indisponível no momento';
      syncAddBtnVariantKey('');
    } else {
      stockLabel.textContent = stock === 1 ? '1 unidade disponível' : stock + ' unidades disponíveis';
      syncAddBtnVariantKey(buildGroupedVariantKey(selected));
    }

    wireQty(ready ? stock : 0);
  }

  selectors.querySelectorAll('.product-variant-option, .product-variant-color').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.disabled || btn.classList.contains('is-disabled')) return;
      var type = btn.getAttribute('data-variant-type');
      var value = btn.getAttribute('data-variant-value');
      var stock = parseInt(btn.getAttribute('data-variant-stock'), 10) || 0;
      var group = btn.closest('.product-variant-options, .product-variant-grid, .product-variant-colors');
      if (!type || !group) return;
      group.querySelectorAll('.product-variant-option, .product-variant-color').forEach(function (b) {
        b.classList.remove('is-selected');
      });
      btn.classList.add('is-selected');
      selected[type] = { value: value, stock: stock };
      updateUi();
    });
  });

  if (qtyMinus && qtyInput) {
    qtyMinus.addEventListener('click', function () {
      var v = (parseInt(qtyInput.value, 10) || 1) - 1;
      qtyInput.value = String(Math.max(1, v));
    });
  }
  if (qtyPlus && qtyInput) {
    qtyPlus.addEventListener('click', function () {
      var max = parseInt(qtyInput.max, 10) || 1;
      var v = (parseInt(qtyInput.value, 10) || 1) + 1;
      qtyInput.value = String(Math.min(max, v));
    });
  }

  updateUi();
})();
