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
  var variantMatrix = { axis: null, colors: [], sizes: [], stock: {} };
  var variantCatalog = window.productVariantCatalog || {};
  var productCoverKey = null;

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
      var sel = document.getElementById('product-vitrine-category');
      if (!sel) return;
      var current = sel.value;
      sel.innerHTML = '<option value="">Sem categoria</option>';
      vitrineCategories.forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = String(c.id);
        opt.textContent = c.name || ('Categoria #' + c.id);
        sel.appendChild(opt);
      });
      if (current) sel.value = current;
    }).catch(function () {
      vitrineCategories = [];
    });
  }

  function setProductCategorySelect(categoryId) {
    var sel = document.getElementById('product-vitrine-category');
    if (!sel) return;
    sel.value = categoryId ? String(categoryId) : '';
  }

  function normalizeLoadedVariantMatrix(matrix) {
    if (!matrix || !matrix.axis) {
      return { axis: null, colors: [], sizes: [], stock: {} };
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
    return { axis: axis, colors: colors, sizes: sizes, stock: stock };
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

  function renderSelectedColors() {
    var el = document.getElementById('variant-color-chips');
    if (!el) return;
    el.innerHTML = '';
    variantMatrix.colors.forEach(function (color) {
      var chip = document.createElement('span');
      chip.className = 'product-variant-value-chip is-selected is-static';
      chip.appendChild(document.createTextNode(color));
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'variant-color-remove';
      rm.textContent = '×';
      rm.setAttribute('aria-label', 'Remover ' + color);
      rm.addEventListener('click', function () {
        variantMatrix.colors = variantMatrix.colors.filter(function (c) { return c !== color; });
        getOrderedSizes().forEach(function (size) {
          if (variantMatrix.stock[size]) delete variantMatrix.stock[size][color];
        });
        renderVariantMatrixUi();
      });
      chip.appendChild(rm);
      el.appendChild(chip);
    });
  }

  function renderColorPicker() {
    var picker = document.getElementById('variant-color-picker-chips');
    if (!picker || !variantCatalog.cor) return;
    picker.innerHTML = '';
    variantCatalog.cor.values.forEach(function (color) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'product-variant-value-chip';
      chip.textContent = color;
      if (variantMatrix.colors.indexOf(color) >= 0) {
        chip.classList.add('is-selected');
      }
      chip.addEventListener('click', function () {
        var idx = variantMatrix.colors.indexOf(color);
        if (idx >= 0) {
          variantMatrix.colors.splice(idx, 1);
          getOrderedSizes().forEach(function (size) {
            if (variantMatrix.stock[size]) delete variantMatrix.stock[size][color];
          });
        } else {
          variantMatrix.colors.push(color);
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

  function renderSizeChips() {
    var el = document.getElementById('variant-size-chips');
    var label = document.getElementById('variant-sizes-label');
    if (!el || !variantMatrix.axis) return;
    var entry = variantCatalog[variantMatrix.axis];
    if (label && entry) label.textContent = entry.label || variantMatrix.axis;
    el.innerHTML = '';
    if (!entry || !entry.values) return;
    entry.values.forEach(function (size) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'product-variant-value-chip';
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
    renderSelectedColors();
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
      variantMatrix = { axis: null, colors: [], sizes: [], stock: {} };
    }
    renderVariantMatrixUi();
  }

  function resetProductVariants() {
    variantMatrix = { axis: null, colors: [], sizes: [], stock: {} };
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
    return {
      axis: variantMatrix.axis,
      colors: variantMatrix.colors.slice(),
      sizes: sizes,
      stock: JSON.parse(JSON.stringify(variantMatrix.stock))
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
        setProductCategorySelect(p.vitrine_category_id);
      }
      api('/api/loja/' + storeSlug + '/products/' + id).then(function (res) {
        if (res && res.id) {
          document.getElementById('product-name').value = res.name || '';
          document.getElementById('product-description').value = res.description || '';
          document.getElementById('product-cost').value = formatBrCurrency(res.cost_price || 0);
          document.getElementById('product-sale').value = formatBrCurrency(res.sale_price || 0);
          document.getElementById('product-stock').value = res.stock_quantity || 0;
          document.getElementById('product-min-stock').value = res.min_stock || 0;
          setProductCategorySelect(res.vitrine_category_id);
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
      setProductCategorySelect('');
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
        var open = picker.classList.toggle('hidden');
        picker.setAttribute('aria-hidden', open ? 'true' : 'false');
        if (!open) renderColorPicker();
      });
    }
    renderVariantMatrixUi();

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('product-id').value;
      var catSel = document.getElementById('product-vitrine-category');
      var catVal = catSel && catSel.value ? parseInt(catSel.value, 10) : null;
      var payload = {
        name: document.getElementById('product-name').value,
        description: document.getElementById('product-description').value,
        cost_price: parseBrCurrency(document.getElementById('product-cost').value),
        sale_price: parseBrCurrency(document.getElementById('product-sale').value),
        stock_quantity: parseInt(document.getElementById('product-stock').value, 10) || 0,
        min_stock: parseInt(document.getElementById('product-min-stock').value, 10) || 0,
        vitrine_category_id: catVal && catVal > 0 ? catVal : null
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
    loadVitrineCategories();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
