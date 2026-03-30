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

  function load() {
    api('/api/loja/' + storeSlug + '/products').then(function (res) {
      currentProducts = res.products || [];
      var listEl = document.getElementById('product-list');
      if (!listEl) return;
      var html = currentProducts.map(function (p, i) {
        var img = (p.images && p.images[0]) ? '<img src="' + p.images[0].url + '" alt="" style="width:32px;height:32px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:6px">' : '';
        var editBtn = readonly ? '' : ' <button type="button" class="btn btn-sm btn-edit-product" data-index="' + i + '">Editar</button>';
        return '<div class="card" style="margin-bottom:0.5rem;padding:0.75rem">' +
          img + '<strong>' + (p.name || '') + '</strong> — R$ ' + parseFloat(p.sale_price).toFixed(2) +
          ' | Estoque: ' + p.stock_quantity + editBtn + '</div>';
      }).join('') || '<p>Nenhum produto.</p>';
      listEl.innerHTML = html;
    });
  }

  function renderPhotosSlide() {
    var slide = document.getElementById('product-photos-slide');
    if (!slide) return;
    slide.innerHTML = '';
    var newFiles = (window._productNewFiles || []).slice();
    var existing = (productExistingImages || []).slice();
    var baseUrl = base.replace(/\/$/, '');
    existing.forEach(function (img, idx) {
      if (!img) return;
      var imgUrl = img.url;
      if (!imgUrl && img.file_path) {
        imgUrl = baseUrl + '/uploads/' + String(img.file_path).replace(/\\/g, '/').replace(/^\//, '');
        if (img.id) imgUrl += '?p=' + encodeURIComponent(String(img.id));
      }
      if (!imgUrl) return;
      var wrap = document.createElement('div');
      wrap.className = 'photo-item';
      var imgEl = document.createElement('img');
      imgEl.src = imgUrl;
      imgEl.alt = '';
      imgEl.onerror = function () { this.style.display = 'none'; };
      wrap.appendChild(imgEl);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'photo-remove';
      btn.title = 'Remover';
      btn.textContent = '×';
      (function (row, pid) {
        btn.onclick = function (ev) {
          if (ev) { ev.preventDefault(); ev.stopPropagation(); }
          var imgId = row && row.id != null ? parseInt(row.id, 10) : 0;
          var productIdInt = pid ? parseInt(pid, 10) : 0;
          if (imgId > 0) {
            productExistingImages = productExistingImages.filter(function (x) {
              return !x || parseInt(x.id, 10) !== imgId;
            });
          } else {
            var i = productExistingImages.indexOf(row);
            if (i >= 0) productExistingImages.splice(i, 1);
          }
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
    newFiles.forEach(function (file, idx) {
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
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'photo-remove';
      btn.title = 'Remover';
      btn.textContent = '×';
      (function (entry) {
        btn.onclick = function (ev) {
          if (ev) { ev.preventDefault(); ev.stopPropagation(); }
          var fileObj = entry && entry.file ? entry.file : entry;
          if (window._productNewFiles && fileObj) {
            window._productNewFiles = window._productNewFiles.filter(function (x) {
              var xf = x && x.file ? x.file : x;
              return xf !== fileObj;
            });
          }
          renderPhotosSlide();
        };
      })(file);
      wrap.appendChild(btn);
      slide.appendChild(wrap);
    });
  }

  function openModal(id) {
    var modal = document.getElementById('product-modal');
    if (!modal) return;
    document.getElementById('modal-title').textContent = id ? 'Editar produto' : 'Novo produto';
    document.getElementById('product-id').value = id || '';
    window._productNewFiles = [];
    productExistingImages = [];
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
      }
      api('/api/loja/' + storeSlug + '/products/' + id).then(function (res) {
        if (res && res.id) {
          document.getElementById('product-name').value = res.name || '';
          document.getElementById('product-description').value = res.description || '';
          document.getElementById('product-cost').value = formatBrCurrency(res.cost_price || 0);
          document.getElementById('product-sale').value = formatBrCurrency(res.sale_price || 0);
          document.getElementById('product-stock').value = res.stock_quantity || 0;
          document.getElementById('product-min-stock').value = res.min_stock || 0;
          productExistingImages = (res.images || []).slice();
        }
        renderPhotosSlide();
      });
    } else {
      document.getElementById('product-form').reset();
      document.getElementById('product-cost').value = '';
      document.getElementById('product-sale').value = '';
    }
    renderPhotosSlide();
    modal.classList.remove('hidden');
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
    if (readonly) {
      if (btnNew) btnNew.style.display = 'none';
      load();
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

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('product-id').value;
      var payload = {
        name: document.getElementById('product-name').value,
        description: document.getElementById('product-description').value,
        cost_price: parseBrCurrency(document.getElementById('product-cost').value),
        sale_price: parseBrCurrency(document.getElementById('product-sale').value),
        stock_quantity: parseInt(document.getElementById('product-stock').value, 10) || 0,
        min_stock: parseInt(document.getElementById('product-min-stock').value, 10) || 0
      };
      if (!id) {
        var newFiles = window._productNewFiles || [];
        var body = { name: payload.name, description: payload.description, cost_price: payload.cost_price, sale_price: payload.sale_price, stock_quantity: payload.stock_quantity, min_stock: payload.min_stock };
        var doCreate = function (imagesBase64) {
          if (imagesBase64 && imagesBase64.length) body.images = imagesBase64;
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
        var newFiles = window._productNewFiles || [];
        if (newFiles.length > 0) {
          return readFilesAsDataUrls(newFiles).then(function (dataUrls) {
            return api('/api/loja/' + storeSlug + '/products/' + id + '/images', {
              method: 'POST',
              body: JSON.stringify({ images: dataUrls })
            });
          }).then(function () {
            modal.classList.add('hidden');
            load();
          });
        }
        modal.classList.add('hidden');
        load();
      });
    });
    load();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
