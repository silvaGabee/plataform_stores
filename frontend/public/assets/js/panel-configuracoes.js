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
