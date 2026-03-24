(function () {
  var root = document.getElementById('meus-enderecos-page');
  if (!root) {
    return;
  }
  var storeSlug = root.getAttribute('data-store-slug');
  if (!storeSlug) {
    return;
  }

  var TXT_HEAD_ADD = 'Adicionar endereço';
  var TXT_HEAD_EDIT = 'Editar endereço';
  var TXT_BTN_SAVE = 'Salvar endereço';
  var TXT_BTN_UPDATE = 'Atualizar endereço';

  function getBaseUrl() {
    var base = (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) ? String(window.BASE_URL) : '';
    if (!base) {
      var meta = document.querySelector('meta[name="base-url"]');
      if (meta && meta.getAttribute('content')) {
        base = meta.getAttribute('content');
      }
    }
    if (!base) {
      var path = window.location.pathname || '';
      var idx = path.indexOf('/loja/');
      if (idx !== -1) {
        base = window.location.origin + path.substring(0, idx);
      } else {
        base = window.location.origin;
      }
    }
    return base.replace(/\/$/, '');
  }

  function api(path, options) {
    var base = getBaseUrl();
    var url = base ? (base + '/' + path.replace(/^\//, '')) : path;
    return fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options
    }).then(function (r) {
      if (!r.ok) {
        return r.text().then(function (text) {
          try {
            var j = JSON.parse(text);
            throw new Error(j.error || text || 'Erro na requisição');
          } catch (e) {
            if (e.message) {
              throw e;
            }
            throw new Error(text || 'Erro ' + r.status);
          }
        });
      }
      return r.json();
    });
  }

  var heading = document.getElementById('meus-enderecos-form-heading');
  var idInput = document.getElementById('meus-addr-id');
  var cancelEditBtn = document.getElementById('meus-enderecos-cancel-edit');
  var submitBtn = document.getElementById('meus-enderecos-submit');
  var msgEl = document.getElementById('meus-enderecos-form-msg');

  function setMsg(text, isError) {
    if (!msgEl) {
      return;
    }
    msgEl.textContent = text || '';
    msgEl.style.color = isError ? 'var(--danger, #ef4444)' : '';
  }

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }

  function setVal(id, v) {
    var el = document.getElementById(id);
    if (el) {
      el.value = v;
    }
  }

  function clearAddressOnlyFields() {
    ['meus-addr-label', 'meus-addr-street', 'meus-addr-number', 'meus-addr-complement', 'meus-addr-neighborhood', 'meus-addr-city', 'meus-addr-state', 'meus-addr-zipcode'].forEach(function (fid) {
      setVal(fid, '');
    });
  }

  function resetEditingUi() {
    if (idInput) {
      idInput.value = '';
    }
    if (heading) {
      heading.textContent = TXT_HEAD_ADD;
    }
    if (submitBtn) {
      submitBtn.textContent = TXT_BTN_SAVE;
    }
    if (cancelEditBtn) {
      cancelEditBtn.classList.add('hidden');
    }
    clearAddressOnlyFields();
  }

  function startEdit(addr) {
    if (!idInput || !heading || !submitBtn || !addr) {
      return;
    }
    idInput.value = String(addr.id);
    heading.textContent = TXT_HEAD_EDIT;
    submitBtn.textContent = TXT_BTN_UPDATE;
    if (cancelEditBtn) {
      cancelEditBtn.classList.remove('hidden');
    }
    setVal('meus-addr-label', addr.label || '');
    setVal('meus-addr-street', addr.street || '');
    setVal('meus-addr-number', addr.number || '');
    setVal('meus-addr-complement', addr.complement || '');
    setVal('meus-addr-neighborhood', addr.neighborhood || '');
    setVal('meus-addr-city', addr.city || '');
    setVal('meus-addr-state', addr.state || '');
    setVal('meus-addr-zipcode', addr.zipcode || '');
    setMsg('');
    root.classList.remove('hidden');
    root.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  if (cancelEditBtn) {
    cancelEditBtn.addEventListener('click', function () {
      resetEditingUi();
      setMsg('');
    });
  }

  var toggle = document.getElementById('meus-enderecos-toggle');
  var panel = document.getElementById('meus-enderecos-page');
  if (toggle && panel) {
    toggle.addEventListener('click', function () {
      panel.classList.toggle('hidden');
      if (!panel.classList.contains('hidden')) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  var list = document.querySelector('.meus-enderecos-list');
  if (list) {
    list.addEventListener('click', function (e) {
      var delBtn = e.target.closest('.meus-enderecos-delete');
      if (delBtn) {
        var aid = delBtn.getAttribute('data-address-id');
        if (!aid || !window.confirm('Excluir este endereço? Esta ação não pode ser desfeita.')) {
          return;
        }
        var emailEl = document.getElementById('meus-addr-email');
        var email = emailEl ? emailEl.value.trim() : '';
        if (!email) {
          alert('E-mail não encontrado.');
          return;
        }
        delBtn.disabled = true;
        api('api/loja/' + encodeURIComponent(storeSlug) + '/checkout/addresses/' + encodeURIComponent(aid), {
          method: 'DELETE',
          body: JSON.stringify({ email: email })
        })
          .then(function () {
            window.location.reload();
          })
          .catch(function (err) {
            delBtn.disabled = false;
            alert(err.message || String(err));
          });
        return;
      }
      var editBtn = e.target.closest('.meus-enderecos-edit');
      if (editBtn) {
        var raw = editBtn.getAttribute('data-address');
        if (!raw) {
          return;
        }
        try {
          startEdit(JSON.parse(raw));
        } catch (err) {
          alert('Não foi possível carregar o endereço para edição.');
        }
      }
    });
  }

  if (!submitBtn) {
    return;
  }

  submitBtn.addEventListener('click', function () {
    var emailEl = document.getElementById('meus-addr-email');
    var email = emailEl ? emailEl.value.trim() : '';
    if (!email) {
      setMsg('E-mail não encontrado. Recarregue a página ou faça login.', true);
      return;
    }
    var nameEl = document.getElementById('meus-addr-customer-name');
    var customerName = nameEl ? nameEl.value.trim() : '';
    if (nameEl && nameEl.hasAttribute('required') && !customerName) {
      setMsg('Informe seu nome.', true);
      return;
    }
    var street = val('meus-addr-street');
    var number = val('meus-addr-number');
    var city = val('meus-addr-city');
    var state = val('meus-addr-state');
    var zipcode = val('meus-addr-zipcode');
    if (!street || !number || !city || !state || !zipcode) {
      setMsg('Preencha Rua, Número, Cidade, UF e CEP.', true);
      return;
    }
    setMsg('');
    submitBtn.disabled = true;
    var prevText = submitBtn.textContent;
    submitBtn.textContent = 'Salvando...';
    var editingId = idInput && idInput.value.trim();
    var payload = {
      email: email,
      customer_name: customerName,
      label: val('meus-addr-label') || '',
      street: street,
      number: number,
      complement: val('meus-addr-complement') || '',
      neighborhood: val('meus-addr-neighborhood') || '',
      city: city,
      state: state,
      zipcode: zipcode
    };
    var url =
      'api/loja/' +
      encodeURIComponent(storeSlug) +
      '/checkout/addresses' +
      (editingId ? '/' + encodeURIComponent(editingId) : '');
    var method = editingId ? 'PUT' : 'POST';
    api(url, {
      method: method,
      body: JSON.stringify(payload)
    })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = prevText;
        setMsg(err.message || String(err), true);
      });
  });
})();
