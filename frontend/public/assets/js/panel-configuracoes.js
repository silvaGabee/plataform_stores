(function () {
  if (typeof storeSlug === 'undefined') {
    return;
  }
  var meta = document.querySelector('meta[name="base-url"]');
  var base = meta && meta.getAttribute('content') ? meta.getAttribute('content').replace(/\/$/, '') : '';

  (function storeName() {
    var form = document.getElementById('config-store-name-form');
    var input = document.getElementById('config-store-name-input');
    var msgEl = document.getElementById('config-store-name-msg');
    var saveBtn = document.getElementById('config-store-name-save');
    if (!form || !input) return;

    var lastSavedName =
      typeof storeNameInitial === 'string' ? storeNameInitial.trim() : input.value.trim();

    function setMsg(text, kind) {
      if (!msgEl) return;
      if (msgEl._timer) {
        clearTimeout(msgEl._timer);
        msgEl._timer = null;
      }
      msgEl.textContent = text || '';
      msgEl.classList.remove('is-error', 'is-success');
      if (kind === 'error') msgEl.classList.add('is-error');
      else if (kind === 'success') {
        msgEl.classList.add('is-success');
        if (text) {
          msgEl._timer = setTimeout(function () {
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
            msgEl._timer = null;
          }, 4200);
        }
      }
    }

    function applyNameEverywhere(name) {
      var pageName = document.getElementById('config-page-store-name');
      if (pageName) pageName.textContent = name;
      var sidebarTitle = document.querySelector('.panel-sidebar-title');
      if (sidebarTitle) sidebarTitle.textContent = name;
      var brandText = document.querySelector('.store-brand-text');
      if (brandText) brandText.textContent = name;
      if (document.title) {
        var parts = document.title.split(' — ');
        if (parts.length > 1) {
          document.title = parts[0] + ' — ' + name;
        }
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = input.value.trim();
      if (name.length < 2) {
        setMsg('O nome deve ter pelo menos 2 caracteres.', 'error');
        input.focus();
        return;
      }
      if (name === lastSavedName) {
        setMsg('Nenhuma alteração no nome.', 'error');
        return;
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'A guardar…';
      }
      setMsg('', '');
      fetch(base + '/api/loja/' + encodeURIComponent(storeSlug) + '/store/name', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ name: name })
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.error) {
            setMsg(res.error, 'error');
            return;
          }
          var saved = res.name || name;
          input.value = saved;
          lastSavedName = saved;
          applyNameEverywhere(saved);
          setMsg('Nome da loja atualizado.', 'success');
        })
        .catch(function () {
          setMsg('Erro de rede.', 'error');
        })
        .finally(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Alterar nome';
          }
        });
    });
  })();

  (function storeAppearance() {
    var form = document.getElementById('config-store-appearance-form');
    var categoriesInput = document.getElementById('config-store-categories-color-input');
    var bannerInput = document.getElementById('config-store-banner-bg-color-input');
    var msgEl = document.getElementById('config-store-appearance-msg');
    var saveBtn = document.getElementById('config-store-appearance-save');
    if (!form || !categoriesInput || !bannerInput) return;

    var initial = typeof storeAppearanceInitial === 'object' && storeAppearanceInitial ? storeAppearanceInitial : {};
    var lastCategories = (initial.categories_background_color || '').trim();
    var lastBanner = (initial.banner_background_color || '').trim();
    if (lastCategories === '') lastCategories = categoriesInput.value || '';
    if (lastBanner === '') lastBanner = bannerInput.value || '';
    if (lastCategories) categoriesInput.value = lastCategories;
    if (lastBanner) bannerInput.value = lastBanner;

    /* Color chooser: palette + advanced (hex + RGB) */
    function hexToRgb(hex) {
      var m = /^#?([0-9a-fA-F]{6})$/.exec(hex);
      if (!m) return null;
      var v = m[1];
      return [parseInt(v.substr(0,2),16), parseInt(v.substr(2,2),16), parseInt(v.substr(4,2),16)];
    }
    function rgbToHex(r,g,b){
      return '#'+[r,g,b].map(function(x){
        var s = Number(x).toString(16);
        return s.length===1? '0'+s : s;
      }).join('');
    }

    function initColorChooser(node) {
      if (!node) return;
      var targetId = node.getAttribute('data-target');
      var colorInput = document.getElementById(targetId);
      if (!colorInput) return;
      var trigger = node.querySelector('.panel-color-trigger');
      var palette = node.querySelector('.panel-color-palette');
      var swatches = node.querySelectorAll('.panel-color-swatch');
      var advToggle = node.querySelector('.panel-color-advanced-toggle');
      var advPanel = node.querySelector('.panel-color-advanced');
      var hexInput = node.querySelector('.panel-color-hex-input');
      var rangeR = node.querySelector('.panel-color-r');
      var rangeG = node.querySelector('.panel-color-g');
      var rangeB = node.querySelector('.panel-color-b');

      function updateAdvancedFromColor(val) {
        var rgb = hexToRgb(val);
        if (rgb) {
          if (hexInput) hexInput.value = val;
          if (rangeR) rangeR.value = rgb[0];
          if (rangeG) rangeG.value = rgb[1];
          if (rangeB) rangeB.value = rgb[2];
        }
      }

      function setColor(val) {
        try { colorInput.value = val; } catch (e) { colorInput.setAttribute('value', val); }
        updateAdvancedFromColor(val);
      }

      // initialize
      updateAdvancedFromColor(colorInput.value || '');

      // large palette card (generated grid) support
      var isLarge = node.getAttribute('data-large-grid') === 'true';
      if (isLarge && palette) {
        palette.style.display = 'none';
        palette.setAttribute('aria-hidden', 'true');
      }
      var card = null;

      function buildColorGrid(cols, rows) {
        var grid = document.createElement('div');
        grid.className = 'panel-color-card-grid';
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(' + cols + ', 20px)';
        grid.style.gap = '6px';
        for (var r = 0; r < rows; r++) {
          for (var c = 0; c < cols; c++) {
            // hue across columns, lightness across rows
            var hue = Math.round((c / cols) * 360);
            var light = Math.round(90 - (r / rows) * 70);
            var col = 'hsl(' + hue + ', 80%,' + light + '%)';
            var sw = document.createElement('button');
            sw.type = 'button';
            sw.className = 'panel-color-card-swatch';
            sw.style.background = col;
            sw.setAttribute('data-color', rgbFromHslString(col) || '');
            sw.addEventListener('click', function (ev) {
              var v = this.getAttribute('data-color');
              if (v) setColor(v);
              closeCard();
            });
            grid.appendChild(sw);
          }
        }
        return grid;
      }

      function rgbFromHslString(hsl) {
        // convert hsl(...) string to hex
        var m = /hsl\((\d+),\s*([0-9.]+)%?,\s*([0-9.]+)%\)/i.exec(hsl);
        if (!m) return null;
        var h = parseInt(m[1], 10) / 360;
        var s = parseFloat(m[2]) / 100;
        var l = parseFloat(m[3]) / 100;
        var r, g, b;
        if (s === 0) {
          r = g = b = l;
        } else {
          var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
          var p = 2 * l - q;
          var hk = h;
          var t = [hk + 1/3, hk, hk - 1/3];
          var rgb = t.map(function(tc) {
            if (tc < 0) tc += 1;
            if (tc > 1) tc -= 1;
            if (tc < 1/6) return p + (q - p) * 6 * tc;
            if (tc < 1/2) return q;
            if (tc < 2/3) return p + (q - p) * (2/3 - tc) * 6;
            return p;
          });
          r = rgb[0]; g = rgb[1]; b = rgb[2];
        }
        var toHex = function(x){
          var s = Math.round(x * 255).toString(16);
          return s.length === 1 ? '0' + s : s;
        };
        return '#' + toHex(r) + toHex(g) + toHex(b);
      }

      function openCard() {
        if (!isLarge || card) return;
        card = document.createElement('div');
        card.className = 'panel-color-card';
        var grid = buildColorGrid(12, 10);
        card.appendChild(grid);
          // if the chooser has an advanced panel, clone it into the card and show it
          if (advPanel) {
            try {
              var advClone = advPanel.cloneNode(true);
              advClone.classList.remove('hidden');
              advClone.setAttribute('aria-hidden', 'false');
              // ensure cloned inputs respond
              card.appendChild(advClone);
              // rewire cloned hex and ranges to update the original input
              (function wireClone(clone){
                var cHex = clone.querySelector('.panel-color-hex-input');
                var cR = clone.querySelector('.panel-color-r');
                var cG = clone.querySelector('.panel-color-g');
                var cB = clone.querySelector('.panel-color-b');
                if (cHex) {
                  cHex.addEventListener('change', function () {
                    var v = cHex.value.trim();
                    if (/^#([0-9a-fA-F]{6})$/.test(v)) setColor(v);
                  });
                }
                [cR, cG, cB].forEach(function (rng) {
                  if (!rng) return;
                  rng.addEventListener('input', function () {
                    var r = cR ? parseInt(cR.value,10) : 0;
                    var g = cG ? parseInt(cG.value,10) : 0;
                    var b = cB ? parseInt(cB.value,10) : 0;
                    setColor(rgbToHex(r,g,b));
                  });
                });
              })(advClone);
            } catch (e) {
              // ignore clone errors
            }
          }
        document.body.appendChild(card);
        // position
        var rect = node.getBoundingClientRect();
        card.style.position = 'absolute';
        card.style.left = (rect.left + window.scrollX) + 'px';
        card.style.top = (rect.bottom + window.scrollY + 8) + 'px';
        card.style.zIndex = 2200;
        card.style.padding = '12px';
        card.style.background = getComputedStyle(document.body).getPropertyValue('--bg-page') || '#fff';
        card.style.border = '1px solid rgba(0,0,0,0.08)';
        card.style.borderRadius = '8px';
        card.style.boxShadow = '0 12px 40px rgba(2,6,23,0.2)';

        // close on outside click
        setTimeout(function(){
          window.addEventListener('click', outsideListener);
        },50);
      }

      function closeCard() {
        if (!card) return;
        window.removeEventListener('click', outsideListener);
        card.remove();
        card = null;
      }

      function outsideListener(e) {
        if (!card) return;
        if (card.contains(e.target) || node.contains(e.target)) return;
        closeCard();
      }

      if (isLarge && trigger) {
        trigger.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          openCard();
        });
      }

      // swatches
      swatches.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var c = btn.getAttribute('data-color');
          if (c) setColor(c);
          // visually mark selected
          swatches.forEach(function(s){ s.classList.remove('is-selected'); });
          btn.classList.add('is-selected');
        });
      });

      // color input change
      colorInput.addEventListener('input', function () {
        updateAdvancedFromColor(colorInput.value);
        swatches.forEach(function(s){ s.classList.remove('is-selected'); });
      });

      // advanced toggle
      if (advToggle && advPanel) {
        advToggle.addEventListener('click', function () {
          var open = !advPanel.classList.contains('hidden');
          advPanel.classList.toggle('hidden', open);
          advPanel.setAttribute('aria-hidden', open ? 'true' : 'false');
          if (!open) {
            // opened
            updateAdvancedFromColor(colorInput.value || '');
            if (hexInput) hexInput.focus();
          }
        });
      }

      // hex input
      if (hexInput) {
        hexInput.addEventListener('change', function () {
          var v = hexInput.value.trim();
          if (/^#([0-9a-fA-F]{6})$/.test(v)) {
            setColor(v);
          }
        });
        hexInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            hexInput.dispatchEvent(new Event('change'));
          }
        });
      }

      // RGB ranges
      [rangeR, rangeG, rangeB].forEach(function (rng) {
        if (!rng) return;
        rng.addEventListener('input', function () {
          var r = rangeR ? parseInt(rangeR.value,10) : 0;
          var g = rangeG ? parseInt(rangeG.value,10) : 0;
          var b = rangeB ? parseInt(rangeB.value,10) : 0;
          var hex = rgbToHex(r,g,b);
          setColor(hex);
        });
      });
    }

    var choosers = document.querySelectorAll('.panel-color-chooser');
    choosers.forEach(initColorChooser);

    function setMsg(text, kind) {
      if (!msgEl) return;
      if (msgEl._timer) {
        clearTimeout(msgEl._timer);
        msgEl._timer = null;
      }
      msgEl.textContent = text || '';
      msgEl.classList.remove('is-error', 'is-success');
      if (kind === 'error') msgEl.classList.add('is-error');
      else if (kind === 'success') {
        msgEl.classList.add('is-success');
        if (text) {
          msgEl._timer = setTimeout(function () {
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
            msgEl._timer = null;
          }, 4200);
        }
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var cat = categoriesInput.value.trim();
      var ban = bannerInput.value.trim();
      if (cat === lastCategories && ban === lastBanner) {
        setMsg('Nenhuma alteração.', 'error');
        return;
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'A guardar…';
      }
      setMsg('', '');
      api('api/loja/' + encodeURIComponent(storeSlug) + '/store/appearance', {
        method: 'POST',
        body: JSON.stringify({ appearance: { categories_background_color: cat, banner_background_color: ban } })
      })
        .then(function (res) {
          var saved = res.appearance || {};
          lastCategories = (saved.categories_background_color || cat || '').trim();
          lastBanner = (saved.banner_background_color || ban || '').trim();
          if (lastCategories) categoriesInput.value = lastCategories;
          if (lastBanner) bannerInput.value = lastBanner;
          setMsg('Aparência atualizada.', 'success');
        })
        .catch(function (err) {
          setMsg(err.message || 'Erro de rede.', 'error');
        })
        .finally(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Alterar aparência';
          }
        });
    });
  })();

  (function storeSlogan() {
    var form = document.getElementById('config-store-slogan-form');
    var input = document.getElementById('config-store-slogan-input');
    var msgEl = document.getElementById('config-store-slogan-msg');
    var saveBtn = document.getElementById('config-store-slogan-save');
    if (!form || !input) return;

    var lastSavedSlogan =
      typeof storeSloganInitial === 'string' ? storeSloganInitial.trim() : input.value.trim();

    function setMsg(text, kind) {
      if (!msgEl) return;
      if (msgEl._timer) {
        clearTimeout(msgEl._timer);
        msgEl._timer = null;
      }
      msgEl.textContent = text || '';
      msgEl.classList.remove('is-error', 'is-success');
      if (kind === 'error') msgEl.classList.add('is-error');
      else if (kind === 'success') {
        msgEl.classList.add('is-success');
        if (text) {
          msgEl._timer = setTimeout(function () {
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
            msgEl._timer = null;
          }, 4200);
        }
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var slogan = input.value.trim();
      if (slogan === lastSavedSlogan) {
        setMsg('Nenhuma alteração no slogan.', 'error');
        return;
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'A guardar…';
      }
      setMsg('', '');
      fetch(base + '/api/loja/' + encodeURIComponent(storeSlug) + '/store/slogan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ slogan: slogan })
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (res.error) {
            setMsg(res.error, 'error');
            return;
          }
          var saved = typeof res.slogan === 'string' ? res.slogan.trim() : slogan;
          input.value = saved;
          lastSavedSlogan = saved;
          setMsg(saved ? 'Slogan atualizado — visível na vitrine.' : 'Slogan removido da vitrine.', 'success');
        })
        .catch(function () {
          setMsg('Erro de rede.', 'error');
        })
        .finally(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Alterar slogan';
          }
        });
    });
  })();

  (function storeBackgroundColor() {
    var form = document.getElementById('config-store-background-form');
    var input = document.getElementById('config-store-background-color-input');
    var msgEl = document.getElementById('config-store-background-msg');
    var saveBtn = document.getElementById('config-store-background-save');
    if (!form || !input) return;

    var lastSavedColor = typeof storeBackgroundColorInitial === 'string' ? storeBackgroundColorInitial.trim() : input.value.trim();
    if (lastSavedColor === '') {
      lastSavedColor = input.value.trim() || '#ffffff';
    }
    input.value = lastSavedColor;

    function setMsg(text, kind) {
      if (!msgEl) return;
      if (msgEl._timer) {
        clearTimeout(msgEl._timer);
        msgEl._timer = null;
      }
      msgEl.textContent = text || '';
      msgEl.classList.remove('is-error', 'is-success');
      if (kind === 'error') msgEl.classList.add('is-error');
      else if (kind === 'success') {
        msgEl.classList.add('is-success');
        if (text) {
          msgEl._timer = setTimeout(function () {
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
            msgEl._timer = null;
          }, 4200);
        }
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var value = input.value.trim();
      if (value === lastSavedColor) {
        setMsg('Nenhuma alteração na cor.', 'error');
        return;
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'A guardar…';
      }
      setMsg('', '');
      api('api/loja/' + encodeURIComponent(storeSlug) + '/store/background-color', {
        method: 'POST',
        body: JSON.stringify({ background_color: value })
      })
        .then(function (res) {
          var saved = typeof res.background_color === 'string' ? res.background_color.trim() : value;
          lastSavedColor = saved || value;
          input.value = lastSavedColor;
          setMsg('Cor de fundo atualizada.', 'success');
        })
        .catch(function (err) {
          setMsg(err.message || 'Erro de rede.', 'error');
        })
        .finally(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Alterar cor';
          }
        });
    });
  })();

  (function storePhoto() {
    var section = document.getElementById('config-store-photo-section');
    if (!section) return;
    var preview = document.getElementById('config-store-photo-preview');
    var fallback = document.getElementById('config-store-photo-fallback');
    var form = document.getElementById('config-store-photo-form');
    var fileInput = document.getElementById('config-store-photo-file');
    var nameEl = document.getElementById('config-store-photo-filename');
    var removeBtn = document.getElementById('config-store-photo-remove');
    var msgEl = document.getElementById('config-store-photo-msg');
    var stage = document.getElementById('config-store-photo-stage');

    function setMsg(text, kind, autoClearSuccessMs) {
      if (!msgEl) return;
      if (msgEl._timer) {
        clearTimeout(msgEl._timer);
        msgEl._timer = null;
      }
      msgEl.textContent = text || '';
      msgEl.classList.remove('is-error', 'is-success');
      if (kind === 'error') msgEl.classList.add('is-error');
      else if (kind === 'success') {
        msgEl.classList.add('is-success');
        var ms = typeof autoClearSuccessMs === 'number' ? autoClearSuccessMs : 4200;
        if (text && ms > 0) {
          msgEl._timer = setTimeout(function () {
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
            msgEl._timer = null;
          }, ms);
        }
      }
    }

    function setFilenameLabel() {
      if (!nameEl || !fileInput) return;
      var f = fileInput.files && fileInput.files[0];
      nameEl.textContent = f ? f.name : 'Nenhum ficheiro novo';
      nameEl.classList.toggle('panel-config-file-name--picked', !!(f && f.name));
    }

    function setHasCustomIcon(hasUrl) {
      if (preview && fallback) {
        if (hasUrl) {
          preview.classList.remove('hidden');
          fallback.classList.add('hidden');
        } else {
          preview.classList.add('hidden');
          fallback.classList.remove('hidden');
          preview.removeAttribute('src');
        }
      }
      if (removeBtn) removeBtn.classList.toggle('hidden', !hasUrl);
      if (stage) stage.classList.toggle('panel-config-store-photo-stage--custom', !!hasUrl);
    }

    fetch(base + '/api/loja/' + encodeURIComponent(storeSlug) + '/store-icon', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.error) {
          setMsg(res.error, 'error');
          setHasCustomIcon(false);
          return;
        }
        if (res.store_icon_url && preview) {
          preview.src = res.store_icon_url + (res.store_icon_url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
          setHasCustomIcon(true);
        } else {
          setHasCustomIcon(false);
        }
      })
      .catch(function () {
        setMsg('Não foi possível carregar a foto da loja.', 'error');
      });

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        setFilenameLabel();
        setMsg('', '');
      });
    }

    if (form && fileInput) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!fileInput.files || !fileInput.files[0]) {
          setMsg('Escolha uma imagem primeiro.', 'error');
          return;
        }
        var fd = new FormData();
        fd.append('store_icon', fileInput.files[0]);
        fetch(base + '/api/loja/' + encodeURIComponent(storeSlug) + '/store-icon', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.error) {
              setMsg(res.error, 'error');
              return;
            }
            setMsg('Foto atualizada.', 'success');
            fileInput.value = '';
            setFilenameLabel();
            if (res.store_icon_url && preview) {
              preview.src = res.store_icon_url;
              setHasCustomIcon(true);
            }
          })
          .catch(function () {
            setMsg('Erro de rede.', 'error');
          });
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        if (!confirm('Remover a foto da loja?')) return;
        fetch(base + '/api/loja/' + encodeURIComponent(storeSlug) + '/store-icon', {
          method: 'DELETE',
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.error) {
              setMsg(res.error, 'error');
              return;
            }
            setMsg('Foto removida.', 'success');
            if (fileInput) fileInput.value = '';
            setFilenameLabel();
            setHasCustomIcon(false);
          })
          .catch(function () {
            setMsg('Erro de rede.', 'error');
          });
      });
    }
  })();

  function api(path, options) {
    var url = base + '/' + path.replace(/^\//, '');
    return fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      ...options
    }).then(function (r) {
      return r.text().then(function (text) {
        var data = {};
        try {
          data = text ? JSON.parse(text) : {};
        } catch (e) {
          data = {};
        }
        if (!r.ok) {
          throw new Error(data.error || text || 'Erro na requisição');
        }
        return data;
      });
    });
  }

  var showBtn = document.getElementById('btn-show-delete-store');
  var confirmBox = document.getElementById('store-delete-confirm');
  var input = document.getElementById('store-delete-confirmation-input');
  var confirmBtn = document.getElementById('btn-confirm-delete-store');
  var cancelBtn = document.getElementById('btn-cancel-delete-store');
  var msgEl = document.getElementById('store-delete-msg');

  function setMsg(text, kind) {
    if (!msgEl) {
      return;
    }
    msgEl.textContent = text || '';
    msgEl.classList.remove('is-error', 'is-success');
    if (kind === 'error') msgEl.classList.add('is-error');
    else if (kind === 'success') msgEl.classList.add('is-success');
    msgEl.style.color = isError ? 'var(--danger, #ef4444)' : '';
  }

  if (showBtn && confirmBox) {
    showBtn.addEventListener('click', function () {
      confirmBox.classList.remove('hidden');
      setMsg('');
      if (input) {
        input.value = '';
        input.focus();
      }
    });
  }

  if (cancelBtn && confirmBox) {
    cancelBtn.addEventListener('click', function () {
      confirmBox.classList.add('hidden');
      if (input) {
        input.value = '';
      }
      setMsg('');
    });
  }

  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      var v = input ? input.value.trim() : '';
      if (v !== 'Excluir') {
        setMsg('Digite Excluir exatamente como mostrado para confirmar.', 'error');
        if (input) {
          input.focus();
        }
        return;
      }
      setMsg('');
      confirmBtn.disabled = true;
      var prev = confirmBtn.textContent;
      confirmBtn.textContent = 'Excluindo...';
      api('api/loja/' + encodeURIComponent(storeSlug) + '/store/delete', {
        method: 'POST',
        body: JSON.stringify({ confirmation: 'Excluir' })
      })
        .then(function (res) {
          window.location.href = res.redirect || (base ? base + '/lojas' : '/lojas');
        })
        .catch(function (err) {
          confirmBtn.disabled = false;
          confirmBtn.textContent = prev;
          setMsg(err.message || String(err), 'error');
        });
    });
  }
})();
