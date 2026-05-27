(function () {
  var main = document.querySelector('.panel-main');
  var storeSlug = (typeof window.storeSlug !== 'undefined' ? window.storeSlug : null) || (main && main.getAttribute('data-store-slug'));
  if (!storeSlug) return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';

  function readFilesAsDataUrls(files) {
    var list = Array.isArray(files) ? files : (files && files.length !== undefined ? [].slice.call(files) : []);
    return Promise.all(list.map(function (file) {
      return new Promise(function (resolve, reject) {
        var fr = new FileReader();
        fr.onload = function () { resolve(fr.result); };
        fr.onerror = reject;
        fr.readAsDataURL(file.file ? file.file : file);
      });
    }));
  }
  function api(path, opt) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    var headers = {};
    if (!(opt && opt.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    return fetch(url, { headers: headers, ...opt }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error(data.error || 'Erro HTTP ' + r.status);
        return data;
      });
    });
  }
  var currentProducts = [];
  var productNewFiles = [];
  var productExistingImages = [];
  var vitrineCategories = [];
  var productVitrineCategoryIds = [];
  var variantMatrix = { axis: null, colors: [], sizes: [], stock: {}, color_meta: {} };
  var variantCatalog = window.productVariantCatalog || {};
  var defaultColorHexMap = window.productVariantDefaultColorHex || {};
  var productCoverKey = null;
  var advancedColorEditing = null;
  var advancedColorTempHex = null;
  var colorPaletteBuilt = false;

  function coverKeyExisting(id) {
    return 'existing:' + id;
  }

  function coverKeyNew(idx) {
    return 'new:' + idx;
  }

  function getCoverImageFromProduct(p) {
    if (!p || !p.images || !p.images.length) return null;
    var cover = p.images.find(function (im) { return im.is_cover; });
    return cover || p.images[0];
  }

  function initDefaultProductCover() {
    var existing = productExistingImages || [];
    var news = window._productNewFiles || [];
    if (existing.length) {
      var cover = existing.find(function (im) { return im.is_cover; }) || existing[0];
      if (cover && cover.id != null) {
        productCoverKey = coverKeyExisting(parseInt(cover.id, 10));
        return;
      }
    }
    if (news.length) {
      productCoverKey = coverKeyNew(0);
      return;
    }
    productCoverKey = null;
  }

  function isCoverKey(key) {
    return productCoverKey === key;
  }

  function validateProductCoverKey() {
    if (!productCoverKey) return;
    if (productCoverKey.indexOf('existing:') === 0) {
      var id = parseInt(productCoverKey.split(':')[1], 10);
      var ok = (productExistingImages || []).some(function (im) {
        return im && parseInt(im.id, 10) === id;
      });
      if (!ok) initDefaultProductCover();
      return;
    }
    if (productCoverKey.indexOf('new:') === 0) {
      var idx = parseInt(productCoverKey.split(':')[1], 10);
      var files = window._productNewFiles || [];
      if (isNaN(idx) || idx < 0 || idx >= files.length) initDefaultProductCover();
    }
  }

  function getOrderedNewFilesForUpload() {
    var files = (window._productNewFiles || []).slice();
    if (!productCoverKey || productCoverKey.indexOf('new:') !== 0) {
      return { files: files, coverIndex: null };
    }
    var idx = parseInt(productCoverKey.split(':')[1], 10);
    if (isNaN(idx) || idx < 0 || idx >= files.length) {
      return { files: files, coverIndex: null };
    }
    if (idx === 0) {
      return { files: files, coverIndex: 0 };
    }
    var out = files.slice();
    var coverFile = out.splice(idx, 1)[0];
    return { files: [coverFile].concat(out), coverIndex: 0 };
  }

  function setProductCover(key) {
    productCoverKey = key;
    renderPhotosSlide();
    if (key.indexOf('existing:') !== 0) return;
    var imageId = parseInt(key.split(':')[1], 10);
    var productId = parseInt(document.getElementById('product-id').value, 10) || 0;
    if (productId <= 0 || imageId <= 0) return;
    api('/api/loja/' + storeSlug + '/products/' + productId + '/cover-image', {
      method: 'POST',
      body: JSON.stringify({ image_id: imageId })
    }).then(function (res) {
      if (res && res.product && res.product.images) {
        productExistingImages = res.product.images.slice();
        var cover = res.product.images.find(function (im) { return im.is_cover; });
        if (cover && cover.id != null) {
          productCoverKey = coverKeyExisting(parseInt(cover.id, 10));
        }
        renderPhotosSlide();
      }
    }).catch(function (err) {
      alert('Erro ao definir capa: ' + (err.message || err));
    });
  }

  function appendPhotoCoverUi(wrap, isCover, onSetCover) {
    if (isCover) {
      var badge = document.createElement('span');
      badge.className = 'photo-cover-badge';
      badge.textContent = 'Capa';
      wrap.appendChild(badge);
      wrap.classList.add('photo-item--cover');
    } else {
      var setBtn = document.createElement('button');
      setBtn.type = 'button';
      setBtn.className = 'photo-set-cover';
      setBtn.textContent = 'Definir capa';
      setBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        onSetCover();
      });
      wrap.appendChild(setBtn);
    }
    wrap.addEventListener('click', function (ev) {
      if (ev.target.closest('.photo-remove') || ev.target.closest('.photo-set-cover')) return;
      onSetCover();
    });
  }

  function sortExistingImagesForDisplay(list) {
    if (!productCoverKey || productCoverKey.indexOf('existing:') !== 0) return list;
    var coverId = parseInt(productCoverKey.split(':')[1], 10);
    return list.slice().sort(function (a, b) {
      var aid = parseInt(a.id, 10);
      var bid = parseInt(b.id, 10);
      if (aid === coverId) return -1;
      if (bid === coverId) return 1;
      return ((a.sort_order || 0) - (b.sort_order || 0)) || (aid - bid);
    });
  }

  function sortNewFilesForDisplay(list) {
    if (!productCoverKey || productCoverKey.indexOf('new:') !== 0) return list;
    var coverIdx = parseInt(productCoverKey.split(':')[1], 10);
    if (isNaN(coverIdx) || coverIdx < 0 || coverIdx >= list.length) return list;
    var out = list.slice();
    var cover = out.splice(coverIdx, 1)[0];
    return [cover].concat(out);
  }

  function mapSortedNewIndexToOriginal(sortedIdx, originalList) {
    if (!productCoverKey || productCoverKey.indexOf('new:') !== 0) return sortedIdx;
    var coverIdx = parseInt(productCoverKey.split(':')[1], 10);
    var sorted = sortNewFilesForDisplay(originalList);
    var fileAt = sorted[sortedIdx];
    return originalList.indexOf(fileAt);
  }

  var readonly = (typeof window.panelProductsReadonly !== 'undefined')
    ? window.panelProductsReadonly === true
    : (typeof window.panelReadonly !== 'undefined' && window.panelReadonly);

  function formatBrCurrency(n) {
    n = parseFloat(n);
    if (isNaN(n)) return '';
    var fixed = Math.max(0, n).toFixed(2);
    var parts = fixed.split('.');
    var intPart = parts[0];
    var decPart = parts[1];
    var formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return formatted + ',' + decPart;
  }

  function parseBrCurrency(s) {
    if (s == null || s === '') return 0;
    var t = String(s).replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
    var n = parseFloat(t);
    return isNaN(n) ? 0 : Math.max(0, n);
  }

  function loadVitrineCategories() {
    return api('/api/loja/' + storeSlug + '/vitrine-categories').then(function (res) {
      vitrineCategories = res.categories || [];
      renderProductVitrineCategories();
    }).catch(function () {
      vitrineCategories = [];
      renderProductVitrineCategories();
    });
  }

  function getSelectedVitrineCategoryIds(excludeIndex) {
    var out = [];
    productVitrineCategoryIds.forEach(function (id, idx) {
      if (excludeIndex !== undefined && idx === excludeIndex) return;
      id = parseInt(id, 10);
      if (id > 0) out.push(id);
    });
    return out;
  }

  function buildVitrineCategorySelect(selectedId, rowIndex) {
    var sel = document.createElement('select');
    sel.className = 'product-vitrine-category-select';
    sel.setAttribute('aria-label', 'Categoria da vitrine');
    var empty = document.createElement('option');
    empty.value = '';
    empty.textContent = 'Sem categoria';
    sel.appendChild(empty);
    var usedElsewhere = getSelectedVitrineCategoryIds(rowIndex);
    vitrineCategories.forEach(function (c) {
      var cid = parseInt(c.id, 10);
      if (usedElsewhere.indexOf(cid) >= 0 && cid !== parseInt(selectedId, 10)) return;
      var opt = document.createElement('option');
      opt.value = String(cid);
      opt.textContent = c.name || ('Categoria #' + cid);
      sel.appendChild(opt);
    });
    sel.value = selectedId && parseInt(selectedId, 10) > 0 ? String(selectedId) : '';
    sel.addEventListener('change', function () {
      var val = sel.value ? parseInt(sel.value, 10) : 0;
      productVitrineCategoryIds[rowIndex] = val;
      renderProductVitrineCategories();
    });
    return sel;
  }

  function renderProductVitrineCategories() {
    var list = document.getElementById('product-vitrine-categories-list');
    if (!list) return;
    if (!productVitrineCategoryIds.length) {
      productVitrineCategoryIds = [0];
    }
    list.innerHTML = '';
    productVitrineCategoryIds.forEach(function (catId, idx) {
      var row = document.createElement('div');
      row.className = 'product-vitrine-category-row';
      row.appendChild(buildVitrineCategorySelect(catId, idx));
      if (productVitrineCategoryIds.length > 1) {
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'product-vitrine-category-remove';
        rm.setAttribute('aria-label', 'Remover categoria');
        rm.textContent = '×';
        rm.addEventListener('click', function () {
          productVitrineCategoryIds.splice(idx, 1);
          if (!productVitrineCategoryIds.length) productVitrineCategoryIds = [0];
          renderProductVitrineCategories();
        });
        row.appendChild(rm);
      }
      list.appendChild(row);
    });
    var addBtn = document.getElementById('product-vitrine-category-add');
    if (addBtn) {
      var filled = getSelectedVitrineCategoryIds().length;
      addBtn.disabled = vitrineCategories.length === 0 || filled >= vitrineCategories.length;
    }
  }

  function addVitrineCategoryRow() {
    var used = getSelectedVitrineCategoryIds();
    if (vitrineCategories.length > 0 && used.length >= vitrineCategories.length) {
      alert('Todas as categorias já foram adicionadas.');
      return;
    }
    productVitrineCategoryIds.push(0);
    renderProductVitrineCategories();
  }

  function setProductVitrineCategories(categoryIds) {
    var ids = [];
    if (Array.isArray(categoryIds)) {
      categoryIds.forEach(function (id) {
        id = parseInt(id, 10);
        if (id > 0 && ids.indexOf(id) < 0) ids.push(id);
      });
    } else if (categoryIds) {
      var single = parseInt(categoryIds, 10);
      if (single > 0) ids.push(single);
    }
    productVitrineCategoryIds = ids.length ? ids : [0];
    renderProductVitrineCategories();
  }

  function getVitrineCategoryIdsPayload() {
    return getSelectedVitrineCategoryIds();
  }

  function normalizeLoadedVariantMatrix(matrix) {
    if (!matrix || !matrix.axis) {
      return { axis: null, colors: [], sizes: [], stock: {}, color_meta: {} };
    }
    var axis = matrix.axis;
    var stock = {};
    try {
      stock = JSON.parse(JSON.stringify(matrix.stock || {}));
    } catch (e) {
      stock = {};
    }
    var colors = [];
    var seenColors = {};
    function addColor(c) {
      if (!c || seenColors[c]) return;
      seenColors[c] = true;
      colors.push(c);
    }
    (matrix.colors || []).forEach(addColor);
    Object.keys(stock).forEach(function (size) {
      var row = stock[size];
      if (!row || typeof row !== 'object') return;
      Object.keys(row).forEach(addColor);
    });
    var sizes = [];
    var seenSizes = {};
    Object.keys(stock).forEach(function (size) {
      if (!size || seenSizes[size]) return;
      seenSizes[size] = true;
      sizes.push(size);
    });
    if (sizes.length === 0) {
      (matrix.sizes || []).forEach(function (size) {
        if (!size || seenSizes[size]) return;
        seenSizes[size] = true;
        sizes.push(size);
      });
    }
    var axisEntry = variantCatalog[axis];
    if (axisEntry && axisEntry.values) {
      sizes.sort(function (a, b) {
        return axisEntry.values.indexOf(a) - axisEntry.values.indexOf(b);
      });
    }
    var corEntry = variantCatalog.cor;
    if (corEntry && corEntry.values) {
      colors.sort(function (a, b) {
        return corEntry.values.indexOf(a) - corEntry.values.indexOf(b);
      });
    }
    var colorMeta = {};
    if (matrix.color_meta && typeof matrix.color_meta === 'object') {
      try {
        colorMeta = JSON.parse(JSON.stringify(matrix.color_meta));
      } catch (e) {
        colorMeta = {};
      }
    }
    colors.forEach(function (c) {
      if (!colorMeta[c]) {
        var def = defaultHexForColor(c);
        if (def) colorMeta[c] = def;
      }
    });
    return { axis: axis, colors: colors, sizes: sizes, stock: stock, color_meta: colorMeta };
  }

  function normalizeHex(hex) {
    if (!hex) return null;
    var s = String(hex).trim();
    if (!s) return null;
    if (s.charAt(0) !== '#') s = '#' + s;
    if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(s)) return null;
    if (s.length === 4) {
      s = '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3];
    }
    return s.toUpperCase();
  }

  function defaultHexForColor(color) {
    return defaultColorHexMap[color] ? normalizeHex(defaultColorHexMap[color]) : null;
  }

  function ensureColorMeta(color) {
    if (!variantMatrix.color_meta) variantMatrix.color_meta = {};
    if (!variantMatrix.color_meta[color]) {
      variantMatrix.color_meta[color] = defaultHexForColor(color) || '#94A3B8';
    }
  }

  function getColorHex(color) {
    if (variantMatrix.color_meta && variantMatrix.color_meta[color]) {
      var fromMeta = normalizeHex(variantMatrix.color_meta[color]);
      if (fromMeta) return fromMeta;
    }
    return defaultHexForColor(color) || '#94A3B8';
  }

  function setColorHex(color, hex) {
    var n = normalizeHex(hex);
    if (!n) return false;
    if (!variantMatrix.color_meta) variantMatrix.color_meta = {};
    variantMatrix.color_meta[color] = n;
    return true;
  }

  function removeColorMeta(color) {
    if (variantMatrix.color_meta) delete variantMatrix.color_meta[color];
  }

  function isLightSwatchColor(color, hex) {
    if (['Branco', 'Amarelo'].indexOf(color) >= 0) return true;
    var h = normalizeHex(hex);
    if (!h || h.length !== 7) return false;
    var r = parseInt(h.slice(1, 3), 16);
    var g = parseInt(h.slice(3, 5), 16);
    var b = parseInt(h.slice(5, 7), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) > 200;
  }

  function hslToHex(h, s, l) {
    s /= 100;
    l /= 100;
    var c = (1 - Math.abs(2 * l - 1)) * s;
    var x = c * (1 - Math.abs((h / 60) % 2 - 1));
    var m = l - c / 2;
    var r = 0;
    var g = 0;
    var b = 0;
    if (h < 60) { r = c; g = x; }
    else if (h < 120) { r = x; g = c; }
    else if (h < 180) { g = c; b = x; }
    else if (h < 240) { g = x; b = c; }
    else if (h < 300) { r = x; b = c; }
    else { r = c; b = x; }
    var toByte = function (v) {
      return Math.round((v + m) * 255).toString(16).padStart(2, '0');
    };
    return ('#' + toByte(r) + toByte(g) + toByte(b)).toUpperCase();
  }

  function buildColorPaletteGrid() {
    var grid = document.getElementById('variant-color-palette-grid');
    if (!grid || colorPaletteBuilt) return;
    colorPaletteBuilt = true;
    grid.innerHTML = '';
    var cols = 20;
    var rows = 12;
    for (var row = 0; row < rows; row++) {
      for (var col = 0; col < cols; col++) {
        var hue = Math.round(col * (360 / cols));
        var sat = 78;
        var light = Math.round(94 - row * (78 / (rows - 1)));
        var hex = hslToHex(hue, sat, light);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'variant-color-palette-cell';
        btn.style.backgroundColor = hex;
        btn.setAttribute('data-hex', hex);
        btn.setAttribute('aria-label', 'Cor ' + hex);
        btn.addEventListener('click', function () {
          var picked = this.getAttribute('data-hex');
          advancedColorTempHex = picked;
          updateAdvancedColorPreview(picked);
          grid.querySelectorAll('.variant-color-palette-cell.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
          });
          this.classList.add('is-selected');
        });
        grid.appendChild(btn);
      }
    }
  }

  function updateAdvancedColorPreview(hex) {
    var swatch = document.getElementById('variant-color-advanced-swatch');
    var hexInput = document.getElementById('variant-color-advanced-hex');
    var norm = normalizeHex(hex) || '#94A3B8';
    if (swatch) {
      swatch.style.setProperty('--swatch', norm);
      swatch.classList.toggle('variant-color-swatch--light', isLightSwatchColor(advancedColorEditing || '', norm));
    }
    if (hexInput) hexInput.value = norm;
  }

  function openColorAdvancedModal(color) {
    advancedColorEditing = color;
    advancedColorTempHex = getColorHex(color);
    buildColorPaletteGrid();
    var modal = document.getElementById('variant-color-advanced-modal');
    var nameEl = document.getElementById('variant-color-advanced-name');
    if (nameEl) nameEl.textContent = color;
    updateAdvancedColorPreview(advancedColorTempHex);
    var grid = document.getElementById('variant-color-palette-grid');
    if (grid) {
      grid.querySelectorAll('.variant-color-palette-cell').forEach(function (cell) {
        var match = normalizeHex(cell.getAttribute('data-hex')) === advancedColorTempHex;
        cell.classList.toggle('is-selected', match);
      });
    }
    if (modal) {
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
    }
  }

  function closeColorAdvancedModal() {
    advancedColorEditing = null;
    advancedColorTempHex = null;
    var modal = document.getElementById('variant-color-advanced-modal');
    if (modal) {
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  function getOrderedSizes() {
    var seen = {};
    var out = [];
    (variantMatrix.sizes || []).forEach(function (size) {
      if (!size || seen[size]) return;
      seen[size] = true;
      out.push(size);
    });
    Object.keys(variantMatrix.stock || {}).forEach(function (size) {
      if (!size || seen[size]) return;
      seen[size] = true;
      out.push(size);
    });
    var axisEntry = variantMatrix.axis && variantCatalog[variantMatrix.axis];
    if (axisEntry && axisEntry.values) {
      out.sort(function (a, b) {
        return axisEntry.values.indexOf(a) - axisEntry.values.indexOf(b);
      });
    }
    variantMatrix.sizes = out;
    return out;
  }

  function variantMatrixTotalStock() {
    var total = 0;
    getOrderedSizes().forEach(function (size) {
      variantMatrix.colors.forEach(function (color) {
        var row = variantMatrix.stock[size] || {};
        total += parseInt(row[color], 10) || 0;
      });
    });
    return total;
  }

  function syncStockFieldFromVariants() {
    var stockEl = document.getElementById('product-stock');
    if (!stockEl) return;
    if (variantMatrix.colors.length > 0 && getOrderedSizes().length > 0) {
      stockEl.value = variantMatrixTotalStock();
      stockEl.classList.add('product-stock--from-variants');
      stockEl.title = 'Soma do estoque das combinações cor + tamanho/numeração';
    } else {
      stockEl.classList.remove('product-stock--from-variants');
      stockEl.title = '';
    }
  }

  function ensureStockRow(size) {
    if (!variantMatrix.stock[size]) variantMatrix.stock[size] = {};
  }

  function isCatalogColor(color) {
    return !!(variantCatalog.cor && variantCatalog.cor.values && variantCatalog.cor.values.indexOf(color) >= 0);
  }

  function isCatalogSize(size) {
    if (!variantMatrix.axis || !variantCatalog[variantMatrix.axis]) return false;
    var values = variantCatalog[variantMatrix.axis].values || [];
    return values.indexOf(size) >= 0;
  }

  /** Zera estoque da matriz (ao abrir «Adicionar cor» evita divergência com valores antigos). */
  function resetVariantStockQuantities() {
    Object.keys(variantMatrix.stock).forEach(function (size) {
      if (!variantMatrix.stock[size]) return;
      variantMatrix.colors.forEach(function (color) {
        variantMatrix.stock[size][color] = 0;
      });
    });
  }

  function addCustomColor() {
    var input = document.getElementById('variant-custom-color-input');
    if (!input) return;
    var name = (input.value || '').trim();
    if (!name) {
      alert('Informe o nome da cor.');
      return;
    }
    if (name.length > 48) {
      alert('Use no máximo 48 caracteres.');
      return;
    }
    if (variantMatrix.colors.indexOf(name) >= 0) {
      alert('Esta cor já foi adicionada.');
      return;
    }
    variantMatrix.colors.push(name);
    ensureColorMeta(name);
    input.value = '';
    getOrderedSizes().forEach(function (size) {
      ensureStockRow(size);
      variantMatrix.stock[size][name] = 0;
    });
    renderVariantMatrixUi();
  }

  function addCustomSize() {
    if (!variantMatrix.axis) {
      alert('Escolha tamanho ou numeração antes.');
      return;
    }
    var input = document.getElementById('variant-custom-size-input');
    if (!input) return;
    var name = (input.value || '').trim();
    if (!name) {
      alert('Informe o valor.');
      return;
    }
    if (name.length > 48) {
      alert('Use no máximo 48 caracteres.');
      return;
    }
    if (variantMatrix.sizes.indexOf(name) >= 0) {
      alert('Esta opção já foi adicionada.');
      return;
    }
    variantMatrix.sizes.push(name);
    input.value = '';
    ensureStockRow(name);
    variantMatrix.colors.forEach(function (color) {
      if (variantMatrix.stock[name][color] === undefined) {
        variantMatrix.stock[name][color] = 0;
      }
    });
    renderVariantMatrixUi();
  }

  function renderColorConfigList() {
    var el = document.getElementById('variant-color-config-list');
    if (!el) return;
    el.innerHTML = '';
    variantMatrix.colors.forEach(function (color) {
      var hex = getColorHex(color);
      var row = document.createElement('div');
      row.className = 'variant-color-config-row' + (isCatalogColor(color) ? '' : ' is-custom');
      row.setAttribute('role', 'listitem');

      var swatch = document.createElement('span');
      swatch.className = 'variant-color-swatch' + (isLightSwatchColor(color, hex) ? ' variant-color-swatch--light' : '');
      swatch.style.setProperty('--swatch', hex);
      swatch.setAttribute('aria-hidden', 'true');

      var name = document.createElement('span');
      name.className = 'variant-color-config-name';
      name.textContent = color;

      var hexWrap = document.createElement('div');
      hexWrap.className = 'variant-color-hex-wrap';
      var hexInput = document.createElement('input');
      hexInput.type = 'text';
      hexInput.className = 'variant-color-hex-input';
      hexInput.value = hex;
      hexInput.maxLength = 7;
      hexInput.placeholder = '#000000';
      hexInput.setAttribute('aria-label', 'Código da cor ' + color);
      hexInput.addEventListener('change', function () {
        if (setColorHex(color, hexInput.value)) {
          var h = getColorHex(color);
          hexInput.value = h;
          swatch.style.setProperty('--swatch', h);
          swatch.classList.toggle('variant-color-swatch--light', isLightSwatchColor(color, h));
        } else {
          hexInput.value = getColorHex(color);
          alert('Use um código hex válido (ex.: #2563EB).');
        }
      });

      var advBtn = document.createElement('button');
      advBtn.type = 'button';
      advBtn.className = 'btn btn-secondary btn-sm variant-color-advanced-btn';
      advBtn.textContent = 'Opções avançadas';
      advBtn.addEventListener('click', function () {
        openColorAdvancedModal(color);
      });

      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'variant-color-remove';
      rm.textContent = '×';
      rm.setAttribute('aria-label', 'Remover ' + color);
      rm.addEventListener('click', function () {
        variantMatrix.colors = variantMatrix.colors.filter(function (c) { return c !== color; });
        removeColorMeta(color);
        getOrderedSizes().forEach(function (size) {
          if (variantMatrix.stock[size]) delete variantMatrix.stock[size][color];
        });
        renderVariantMatrixUi();
      });

      hexWrap.appendChild(hexInput);
      row.appendChild(swatch);
      row.appendChild(name);
      row.appendChild(hexWrap);
      row.appendChild(advBtn);
      row.appendChild(rm);
      el.appendChild(row);
    });
  }

  function renderColorPicker() {
    var picker = document.getElementById('variant-color-picker-chips');
    if (!picker || !variantCatalog.cor) return;
    picker.innerHTML = '';
    variantCatalog.cor.values.forEach(function (color) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'product-variant-value-chip product-variant-value-chip--with-swatch';
      var dot = document.createElement('span');
      dot.className = 'variant-color-swatch variant-color-swatch--chip' + (isLightSwatchColor(color, getColorHex(color)) ? ' variant-color-swatch--light' : '');
      dot.style.setProperty('--swatch', getColorHex(color));
      dot.setAttribute('aria-hidden', 'true');
      chip.appendChild(dot);
      chip.appendChild(document.createTextNode(color));
      if (variantMatrix.colors.indexOf(color) >= 0) {
        chip.classList.add('is-selected');
      }
      chip.addEventListener('click', function () {
        var idx = variantMatrix.colors.indexOf(color);
        if (idx >= 0) {
          variantMatrix.colors.splice(idx, 1);
          removeColorMeta(color);
          getOrderedSizes().forEach(function (size) {
            if (variantMatrix.stock[size]) delete variantMatrix.stock[size][color];
          });
        } else {
          variantMatrix.colors.push(color);
          ensureColorMeta(color);
          getOrderedSizes().forEach(function (size) {
            ensureStockRow(size);
            if (variantMatrix.stock[size][color] === undefined) {
              variantMatrix.stock[size][color] = 0;
            }
          });
        }
        renderVariantMatrixUi();
      });
      picker.appendChild(chip);
    });
  }

  function renderAxisButtons() {
    var box = document.getElementById('variant-axis-btns');
    if (!box) return;
    box.innerHTML = '';
    ['tamanho', 'numeracao'].forEach(function (axis) {
      var entry = variantCatalog[axis];
      if (!entry) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'product-variant-type-btn' + (variantMatrix.axis === axis ? ' is-selected' : '');
      btn.textContent = entry.label || axis;
      btn.addEventListener('click', function () {
        if (variantMatrix.axis === axis) return;
        variantMatrix.axis = axis;
        variantMatrix.sizes = [];
        variantMatrix.stock = {};
        renderVariantMatrixUi();
      });
      box.appendChild(btn);
    });
  }

  function appendSizeChip(el, size, isCustom) {
    var chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'product-variant-value-chip' + (isCustom ? ' is-custom' : '');
    chip.textContent = size;
    if (variantMatrix.sizes.indexOf(size) >= 0) chip.classList.add('is-selected');
    chip.addEventListener('click', function () {
      var idx = variantMatrix.sizes.indexOf(size);
      if (idx >= 0) {
        variantMatrix.sizes.splice(idx, 1);
        delete variantMatrix.stock[size];
      } else {
        if (variantMatrix.sizes.indexOf(size) < 0) {
          variantMatrix.sizes.push(size);
        }
        ensureStockRow(size);
        variantMatrix.colors.forEach(function (color) {
          if (variantMatrix.stock[size][color] === undefined) {
            variantMatrix.stock[size][color] = 0;
          }
        });
      }
      renderVariantMatrixUi();
    });
    el.appendChild(chip);
  }

  function renderSizeChips() {
    var el = document.getElementById('variant-size-chips');
    var label = document.getElementById('variant-sizes-label');
    var customLabel = document.getElementById('variant-add-custom-size-label');
    var customInput = document.getElementById('variant-custom-size-input');
    if (!el || !variantMatrix.axis) return;
    var entry = variantCatalog[variantMatrix.axis];
    if (label && entry) label.textContent = entry.label || variantMatrix.axis;
    if (customLabel && entry) {
      customLabel.textContent = variantMatrix.axis === 'numeracao' ? 'Nova numeração' : 'Novo tamanho';
    }
    if (customInput) {
      customInput.placeholder = variantMatrix.axis === 'numeracao' ? 'Ex.: 46, 47.5' : 'Ex.: XXL, Infantil';
    }
    el.innerHTML = '';
    if (!entry || !entry.values) return;
    entry.values.forEach(function (size) {
      appendSizeChip(el, size, false);
    });
    variantMatrix.sizes.forEach(function (size) {
      if (!isCatalogSize(size)) {
        appendSizeChip(el, size, true);
      }
    });
  }

  function renderStockMatrix() {
    var table = document.getElementById('variant-stock-matrix');
    var axisName = document.getElementById('variant-stock-axis-name');
    if (!table) return;
    var thead = table.querySelector('thead');
    var tbody = table.querySelector('tbody');
    if (!thead || !tbody) return;
    if (axisName && variantMatrix.axis && variantCatalog[variantMatrix.axis]) {
      axisName.textContent = (variantCatalog[variantMatrix.axis].label || variantMatrix.axis).toLowerCase();
    }
    if (variantMatrix.colors.length === 0 || getOrderedSizes().length === 0) {
      thead.innerHTML = '';
      tbody.innerHTML = '';
      return;
    }
    var headRow = '<tr><th scope="col">' + (variantMatrix.axis === 'numeracao' ? 'Nº' : 'Tam.') + '</th>';
    variantMatrix.colors.forEach(function (color) {
      headRow += '<th scope="col">' + color.replace(/</g, '&lt;') + '</th>';
    });
    headRow += '</tr>';
    thead.innerHTML = headRow;
    tbody.innerHTML = '';
    getOrderedSizes().forEach(function (size) {
      ensureStockRow(size);
      var tr = document.createElement('tr');
      var th = document.createElement('th');
      th.scope = 'row';
      th.textContent = size;
      tr.appendChild(th);
      variantMatrix.colors.forEach(function (color) {
        var td = document.createElement('td');
        var input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.step = '1';
        input.className = 'variant-stock-input';
        input.value = String(parseInt(variantMatrix.stock[size][color], 10) || 0);
        input.setAttribute('aria-label', color + ' ' + size);
        input.addEventListener('change', function () {
          var v = parseInt(input.value, 10);
          variantMatrix.stock[size][color] = isNaN(v) || v < 0 ? 0 : v;
          syncStockFieldFromVariants();
        });
        td.appendChild(input);
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    syncStockFieldFromVariants();
  }

  function renderVariantMatrixUi() {
    renderColorConfigList();
    renderColorPicker();
    var axisBlock = document.getElementById('variant-axis-block');
    var sizesBlock = document.getElementById('variant-sizes-block');
    var stockBlock = document.getElementById('variant-stock-block');
    var hasColors = variantMatrix.colors.length > 0;
    if (axisBlock) {
      axisBlock.classList.toggle('hidden', !hasColors);
      axisBlock.setAttribute('aria-hidden', hasColors ? 'false' : 'true');
    }
    if (hasColors) renderAxisButtons();
    var hasAxis = !!variantMatrix.axis;
    if (sizesBlock) {
      sizesBlock.classList.toggle('hidden', !hasAxis);
      sizesBlock.setAttribute('aria-hidden', hasAxis ? 'false' : 'true');
    }
    if (hasAxis) renderSizeChips();
    var hasSizes = getOrderedSizes().length > 0;
    if (stockBlock) {
      stockBlock.classList.toggle('hidden', !hasSizes);
      stockBlock.setAttribute('aria-hidden', hasSizes ? 'false' : 'true');
    }
    if (hasSizes) renderStockMatrix();
    else syncStockFieldFromVariants();
  }

  function setProductVariantsFromApi(data) {
    var matrix = (data && data.variants_matrix) ? data.variants_matrix : null;
    if (matrix && matrix.colors && matrix.colors.length) {
      variantMatrix = normalizeLoadedVariantMatrix(matrix);
    } else {
      variantMatrix = { axis: null, colors: [], sizes: [], stock: {}, color_meta: {} };
    }
    renderVariantMatrixUi();
  }

  function resetProductVariants() {
    variantMatrix = { axis: null, colors: [], sizes: [], stock: {}, color_meta: {} };
    var picker = document.getElementById('variant-color-picker');
    if (picker) {
      picker.classList.add('hidden');
      picker.setAttribute('aria-hidden', 'true');
    }
    renderVariantMatrixUi();
  }

  function getVariantsMatrixPayload() {
    if (variantMatrix.colors.length === 0 || !variantMatrix.axis) {
      return null;
    }
    var sizes = getOrderedSizes();
    if (sizes.length === 0) {
      return null;
    }
    var colorMeta = {};
    variantMatrix.colors.forEach(function (color) {
      var hex = getColorHex(color);
      if (hex) colorMeta[color] = hex;
    });
    return {
      axis: variantMatrix.axis,
      colors: variantMatrix.colors.slice(),
      sizes: sizes,
      stock: JSON.parse(JSON.stringify(variantMatrix.stock)),
      color_meta: colorMeta
    };
  }

  function load() {
    var listEl = document.getElementById('product-list');
    if (!listEl) return;
    api('/api/loja/' + storeSlug + '/products').then(function (res) {
      currentProducts = res.products || [];
      var html = currentProducts.map(function (p, i) {
        var coverIm = getCoverImageFromProduct(p);
        var img = coverIm && coverIm.url
          ? '<span class="panel-product-row-thumb"><img src="' + coverIm.url + '" alt=""></span>'
          : '<span class="panel-product-row-thumb panel-product-row-thumb--empty" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" stroke="currentColor" stroke-width="1.25" opacity="0.45"/></svg></span>';
        var editBtn = readonly ? '' : '<button type="button" class="btn btn-secondary btn-sm btn-edit-product" data-index="' + i + '">Editar</button>';
        return '<article class="panel-product-row card">' +
          '<div class="panel-product-row-main">' + img +
          '<div class="panel-product-row-text"><strong class="panel-product-row-name">' + (p.name || '').replace(/</g, '&lt;') + '</strong>' +
          '<span class="panel-product-row-meta">R$ ' + parseFloat(p.sale_price).toFixed(2).replace('.', ',') +
          ' · Estoque: ' + p.stock_quantity + '</span></div></div>' + editBtn + '</article>';
      }).join('') || '<div class="panel-products-empty"><p>Nenhum produto cadastrado.</p><p class="text-muted">Clique em <strong>Novo produto</strong> para começar.</p></div>';
      listEl.innerHTML = html;
    }).catch(function (err) {
      listEl.innerHTML = '<div class="panel-products-empty"><p>Não foi possível carregar os produtos.</p><p class="text-muted">' + (err && err.message ? String(err.message).replace(/</g, '&lt;') : 'Erro desconhecido') + '</p></div>';
    });
  }

  function renderPhotosSlide() {
    var slide = document.getElementById('product-photos-slide');
    if (!slide) return;
    validateProductCoverKey();
    if (!productCoverKey) initDefaultProductCover();
    slide.innerHTML = '';
    var newFilesRaw = (window._productNewFiles || []).slice();
    var newFiles = sortNewFilesForDisplay(newFilesRaw);
    var existing = sortExistingImagesForDisplay(productExistingImages || []);
    var baseUrl = base.replace(/\/$/, '');
    existing.forEach(function (img, idx) {
      if (!img) return;
      var imgUrl = img.url;
      if (!imgUrl && img.file_path) {
        imgUrl = baseUrl + '/uploads/' + String(img.file_path).replace(/\\/g, '/').replace(/^\//, '');
        if (img.id) imgUrl += '?p=' + encodeURIComponent(String(img.id));
      }
      if (!imgUrl) return;
      var imgId = img.id != null ? parseInt(img.id, 10) : 0;
      var wrap = document.createElement('div');
      wrap.className = 'photo-item';
      var imgEl = document.createElement('img');
      imgEl.src = imgUrl;
      imgEl.alt = '';
      imgEl.onerror = function () { this.style.display = 'none'; };
      wrap.appendChild(imgEl);
      appendPhotoCoverUi(wrap, isCoverKey(coverKeyExisting(imgId)), function () {
        setProductCover(coverKeyExisting(imgId));
      });
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'photo-remove';
      btn.title = 'Remover';
      btn.textContent = '×';
      (function (row, pid) {
        btn.onclick = function (ev) {
          if (ev) { ev.preventDefault(); ev.stopPropagation(); }
          var removedId = row && row.id != null ? parseInt(row.id, 10) : 0;
          var productIdInt = pid ? parseInt(pid, 10) : 0;
          if (removedId > 0) {
            productExistingImages = productExistingImages.filter(function (x) {
              return !x || parseInt(x.id, 10) !== removedId;
            });
            if (productCoverKey === coverKeyExisting(removedId)) {
              productCoverKey = null;
            }
          } else {
            var i = productExistingImages.indexOf(row);
            if (i >= 0) productExistingImages.splice(i, 1);
          }
          initDefaultProductCover();
          renderPhotosSlide();
          if (productIdInt > 0 && imgId > 0) {
            var path = '/api/loja/' + encodeURIComponent(storeSlug) + '/product-image-delete';
            var delUrl = baseUrl + path;
            fetch(delUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({ product_id: productIdInt, image_id: imgId })
            }).then(function (r) {
              return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.error || ('HTTP ' + r.status));
                return data;
              });
            }).then(function () {
              load();
              return api('/api/loja/' + storeSlug + '/products/' + productIdInt);
            }).then(function (res) {
              if (res && res.images) productExistingImages = res.images.slice();
              renderPhotosSlide();
            }).catch(function (err) {
              alert('Erro ao remover foto: ' + (err.message || err));
              api('/api/loja/' + storeSlug + '/products/' + productIdInt).then(function (res) {
                if (res && res.images) productExistingImages = res.images.slice();
                renderPhotosSlide();
              });
            });
          }
        };
      })(img, document.getElementById('product-id').value);
      wrap.appendChild(btn);
      slide.appendChild(wrap);
    });
    newFiles.forEach(function (file, sortedIdx) {
      var origIdx = mapSortedNewIndexToOriginal(sortedIdx, newFilesRaw);
      var wrap = document.createElement('div');
      wrap.className = 'photo-item';
      var f = file && file.file ? file.file : file;
      try {
        var url = URL.createObjectURL(f);
        var imgEl = document.createElement('img');
        imgEl.src = url;
        imgEl.alt = '';
        wrap.appendChild(imgEl);
      } catch (err) {
        var span = document.createElement('span');
        span.className = 'photo-fallback';
        span.textContent = (f && f.name) || 'Foto';
        wrap.appendChild(span);
      }
      appendPhotoCoverUi(wrap, isCoverKey(coverKeyNew(origIdx)), function () {
        setProductCover(coverKeyNew(origIdx));
      });
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'photo-remove';
      btn.title = 'Remover';
      btn.textContent = '×';
      (function (entry, removedOrigIdx) {
        btn.onclick = function (ev) {
          if (ev) { ev.preventDefault(); ev.stopPropagation(); }
          var fileObj = entry && entry.file ? entry.file : entry;
          if (window._productNewFiles && fileObj) {
            window._productNewFiles = window._productNewFiles.filter(function (x) {
              var xf = x && x.file ? x.file : x;
              return xf !== fileObj;
            });
          }
          if (productCoverKey === coverKeyNew(removedOrigIdx)) {
            productCoverKey = null;
          } else if (productCoverKey && productCoverKey.indexOf('new:') === 0) {
            var cIdx = parseInt(productCoverKey.split(':')[1], 10);
            if (!isNaN(cIdx) && cIdx > removedOrigIdx) {
              productCoverKey = coverKeyNew(cIdx - 1);
            }
          }
          initDefaultProductCover();
          renderPhotosSlide();
        };
      })(file, origIdx);
      wrap.appendChild(btn);
      slide.appendChild(wrap);
    });
  }

  function openModal(id) {
    var modal = document.getElementById('product-modal');
    if (!modal) return;
    loadVitrineCategories().then(function () {
    document.getElementById('modal-title').textContent = id ? 'Editar produto' : 'Novo produto';
    document.getElementById('product-id').value = id || '';
    window._productNewFiles = [];
    productExistingImages = [];
    productCoverKey = null;
    resetProductVariants();
    var slideEl = document.getElementById('product-photos-slide');
    if (slideEl) slideEl.innerHTML = '';
    if (id) {
      var p = currentProducts.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
      if (p) {
        document.getElementById('product-name').value = p.name || '';
        document.getElementById('product-description').value = p.description || '';
        document.getElementById('product-cost').value = formatBrCurrency(p.cost_price || 0);
        document.getElementById('product-sale').value = formatBrCurrency(p.sale_price || 0);
        document.getElementById('product-stock').value = p.stock_quantity || 0;
        document.getElementById('product-min-stock').value = p.min_stock || 0;
        setProductVitrineCategories(p.vitrine_category_ids || p.vitrine_category_id);
      }
      api('/api/loja/' + storeSlug + '/products/' + id).then(function (res) {
        if (res && res.id) {
          document.getElementById('product-name').value = res.name || '';
          document.getElementById('product-description').value = res.description || '';
          document.getElementById('product-cost').value = formatBrCurrency(res.cost_price || 0);
          document.getElementById('product-sale').value = formatBrCurrency(res.sale_price || 0);
          document.getElementById('product-stock').value = res.stock_quantity || 0;
          document.getElementById('product-min-stock').value = res.min_stock || 0;
          setProductVitrineCategories(res.vitrine_category_ids || res.vitrine_category_id);
          productExistingImages = (res.images || []).slice();
          setProductVariantsFromApi(res);
          initDefaultProductCover();
        }
        renderPhotosSlide();
      });
    } else {
      document.getElementById('product-form').reset();
      document.getElementById('product-cost').value = '';
      document.getElementById('product-sale').value = '';
      setProductVitrineCategories([]);
      resetProductVariants();
    }
    renderPhotosSlide();
    modal.classList.remove('hidden');
    });
  }

  function init() {
    var listEl = document.getElementById('product-list');
    var btnNew = document.getElementById('btn-new-product');
    var modal = document.getElementById('product-modal');
    var form = document.getElementById('product-form');
    var photosInput = document.getElementById('product-photos-input');
    var photosAdd = document.getElementById('product-photos-add');
    var photosSlide = document.getElementById('product-photos-slide');

    window._renderProductPhotosSlide = renderPhotosSlide;

    if (!listEl) return;
    load();
    if (readonly) {
      if (btnNew) btnNew.style.display = 'none';
      return;
    }
    if (!modal || !form || !btnNew) return;

    listEl.addEventListener('click', function (e) {
      var btnEdit = e.target.closest('.btn-edit-product');
      if (btnEdit) {
        e.preventDefault();
        var i = parseInt(btnEdit.getAttribute('data-index'), 10);
        if (!isNaN(i) && currentProducts[i]) openModal(currentProducts[i].id);
      }
    });

    btnNew.addEventListener('click', function () { openModal(0); });
    modal.querySelectorAll('.close-modal').forEach(function (b) {
      b.addEventListener('click', function () { modal.classList.add('hidden'); });
    });
    var costEl = document.getElementById('product-cost');
    var saleEl = document.getElementById('product-sale');
    if (costEl) costEl.addEventListener('blur', function () { if (this.value) this.value = formatBrCurrency(parseBrCurrency(this.value)); });
    if (saleEl) saleEl.addEventListener('blur', function () { if (this.value) this.value = formatBrCurrency(parseBrCurrency(this.value)); });

    var variantToggleColor = document.getElementById('variant-toggle-color-picker');
    if (variantToggleColor) {
      variantToggleColor.addEventListener('click', function () {
        var picker = document.getElementById('variant-color-picker');
        if (!picker) return;
        var wasHidden = picker.classList.contains('hidden');
        if (wasHidden) {
          resetVariantStockQuantities();
          renderStockMatrix();
        }
        var open = picker.classList.toggle('hidden');
        picker.setAttribute('aria-hidden', open ? 'true' : 'false');
        if (!open) renderColorPicker();
      });
    }
    var addCustomColorBtn = document.getElementById('variant-add-custom-color');
    var customColorInput = document.getElementById('variant-custom-color-input');
    if (addCustomColorBtn) addCustomColorBtn.addEventListener('click', addCustomColor);
    if (customColorInput) {
      customColorInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addCustomColor();
        }
      });
    }
    var addCustomSizeBtn = document.getElementById('variant-add-custom-size');
    var customSizeInput = document.getElementById('variant-custom-size-input');
    if (addCustomSizeBtn) addCustomSizeBtn.addEventListener('click', addCustomSize);
    if (customSizeInput) {
      customSizeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addCustomSize();
        }
      });
    }
    buildColorPaletteGrid();
    document.querySelectorAll('[data-close-advanced-color]').forEach(function (btn) {
      btn.addEventListener('click', closeColorAdvancedModal);
    });
    var advancedApply = document.getElementById('variant-color-advanced-apply');
    if (advancedApply) {
      advancedApply.addEventListener('click', function () {
        if (!advancedColorEditing) return;
        var hexInput = document.getElementById('variant-color-advanced-hex');
        var hex = advancedColorTempHex || (hexInput && hexInput.value);
        if (!setColorHex(advancedColorEditing, hex)) {
          alert('Selecione uma cor na paleta ou informe um código hex válido.');
          return;
        }
        closeColorAdvancedModal();
        renderVariantMatrixUi();
      });
    }
    var advancedHexInput = document.getElementById('variant-color-advanced-hex');
    if (advancedHexInput) {
      advancedHexInput.addEventListener('change', function () {
        if (normalizeHex(advancedHexInput.value)) {
          advancedColorTempHex = normalizeHex(advancedHexInput.value);
          updateAdvancedColorPreview(advancedColorTempHex);
        }
      });
    }

    renderVariantMatrixUi();

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('product-id').value;
      var categoryIds = getVitrineCategoryIdsPayload();
      var payload = {
        name: document.getElementById('product-name').value,
        description: document.getElementById('product-description').value,
        cost_price: parseBrCurrency(document.getElementById('product-cost').value),
        sale_price: parseBrCurrency(document.getElementById('product-sale').value),
        stock_quantity: parseInt(document.getElementById('product-stock').value, 10) || 0,
        min_stock: parseInt(document.getElementById('product-min-stock').value, 10) || 0,
        vitrine_category_ids: categoryIds,
        vitrine_category_id: categoryIds.length ? categoryIds[0] : null
      };
      var matrixPayload = getVariantsMatrixPayload();
      if (matrixPayload) {
        payload.variants_matrix = matrixPayload;
        payload.stock_quantity = variantMatrixTotalStock();
      } else {
        payload.variants_matrix = null;
        payload.variants = [];
      }
      if (!id) {
        var uploadOrder = getOrderedNewFilesForUpload();
        var newFiles = uploadOrder.files;
        var body = {
          name: payload.name,
          description: payload.description,
          cost_price: payload.cost_price,
          sale_price: payload.sale_price,
          stock_quantity: payload.stock_quantity,
          min_stock: payload.min_stock,
          vitrine_category_ids: payload.vitrine_category_ids,
          vitrine_category_id: payload.vitrine_category_id,
          variants_matrix: payload.variants_matrix,
          variants: payload.variants || []
        };
        var doCreate = function (imagesBase64) {
          if (imagesBase64 && imagesBase64.length) {
            body.images = imagesBase64;
            if (uploadOrder.coverIndex != null && uploadOrder.coverIndex > 0) {
              body.cover_index = uploadOrder.coverIndex;
            }
          }
          return api('/api/loja/' + storeSlug + '/products', { method: 'POST', body: JSON.stringify(body) });
        };
        var p = newFiles.length > 0
          ? readFilesAsDataUrls(newFiles).then(function (dataUrls) { return doCreate(dataUrls); })
          : doCreate();
        p.then(function (res) {
          if (res && res.error) { alert(res.error); return; }
          modal.classList.add('hidden');
          load();
        }).catch(function (err) {
          alert('Erro ao salvar: ' + (err.message || err));
        });
        return;
      }
      api('/api/loja/' + storeSlug + '/products/' + id, { method: 'PUT', body: JSON.stringify(payload) }).then(function (res) {
        if (res.error) { alert(res.error); return; }
        var uploadOrder = getOrderedNewFilesForUpload();
        var newFiles = uploadOrder.files;
        if (newFiles.length > 0) {
          return readFilesAsDataUrls(newFiles).then(function (dataUrls) {
            var imgBody = { images: dataUrls };
            if (productCoverKey && productCoverKey.indexOf('new:') === 0) {
              imgBody.cover_index = uploadOrder.coverIndex != null ? uploadOrder.coverIndex : 0;
            }
            return api('/api/loja/' + storeSlug + '/products/' + id + '/images', {
              method: 'POST',
              body: JSON.stringify(imgBody)
            });
          }).then(function (res) {
            if (res && res.product && res.product.images) {
              productExistingImages = res.product.images.slice();
              initDefaultProductCover();
            }
            modal.classList.add('hidden');
            load();
          });
        }
        modal.classList.add('hidden');
        load();
      });
    });
    var addCatBtn = document.getElementById('product-vitrine-category-add');
    if (addCatBtn) addCatBtn.addEventListener('click', addVitrineCategoryRow);
    loadVitrineCategories();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
