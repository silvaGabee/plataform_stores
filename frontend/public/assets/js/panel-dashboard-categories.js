(function () {
  var section = document.getElementById('dashboard-vitrine-categories-section');
  if (!section) return;

  var meta = document.querySelector('meta[name="base-url"]');
  var base = meta && meta.getAttribute('content') ? meta.getAttribute('content').replace(/\/$/, '') : '';
  var slug =
    typeof storeSlug !== 'undefined'
      ? storeSlug
      : (document.querySelector('.panel-main') || {}).getAttribute('data-store-slug') || '';

  var listEl = document.getElementById('dashboard-categories-list');
  var emptyEl = document.getElementById('dashboard-categories-empty');
  var msgEl = document.getElementById('dashboard-categories-msg');
  var modal = document.getElementById('dashboard-category-modal');
  var addBtn = document.getElementById('dashboard-categories-add-btn');
  var form = document.getElementById('dashboard-category-form');
  var nameInput = document.getElementById('dashboard-category-name');
  var iconKeyInput = document.getElementById('dashboard-category-icon-key');
  var pickerEl = document.getElementById('dashboard-category-icon-picker');
  var modalMsg = document.getElementById('dashboard-category-modal-msg');
  var closeBtn = document.getElementById('dashboard-category-modal-close');
  var cancelBtn = document.getElementById('dashboard-category-cancel');
  var saveBtn = document.getElementById('dashboard-category-save');

  var iconCatalog = [];
  var categories = [];
  var canManage = !!(addBtn && form && modal);

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function setMsg(el, text, kind) {
    if (!el) return;
    el.textContent = text || '';
    el.classList.remove('is-error', 'is-success');
    if (kind === 'error') el.classList.add('is-error');
    else if (kind === 'success') el.classList.add('is-success');
  }

  function renderList() {
    if (!listEl || !emptyEl) return;
    if (!categories.length) {
      listEl.innerHTML = '';
      listEl.hidden = true;
      emptyEl.classList.remove('hidden');
      return;
    }
    emptyEl.classList.add('hidden');
    listEl.hidden = false;
    listEl.innerHTML = categories
      .map(function (c) {
        var del =
          canManage && !window.panelReadonly
            ? '<button type="button" class="dashboard-category-remove" data-id="' +
              c.id +
              '" title="Remover categoria" aria-label="Remover ' +
              esc(c.name) +
              '">' +
              '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
              '</button>'
            : '';
        return (
          '<li class="dashboard-category-chip" role="listitem">' +
          '<div class="dashboard-category-chip-visual">' +
          '<span class="vitrine-category-icon vitrine-category-icon--panel">' +
          '<img src="' +
          esc(c.icon_url) +
          '" alt="" width="26" height="26" decoding="async" loading="lazy" referrerpolicy="no-referrer">' +
          '</span>' +
          del +
          '</div>' +
          '<span class="dashboard-category-chip-name" title="' +
          esc(c.name) +
          '">' +
          esc(c.name) +
          '</span>' +
          '</li>'
        );
      })
      .join('');

    if (typeof window.initHScrollMask === 'function') {
      window.initHScrollMask(section);
    }

    listEl.querySelectorAll('.dashboard-category-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id || !confirm('Remover esta categoria da vitrine?')) return;
        fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/vitrine-categories/' + id, {
          method: 'DELETE',
          credentials: 'same-origin'
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (res) {
            if (res.error) {
              setMsg(msgEl, res.error, 'error');
              return;
            }
            categories = categories.filter(function (c) {
              return c.id !== id;
            });
            renderList();
            setMsg(msgEl, 'Categoria removida.', 'success');
          })
          .catch(function () {
            setMsg(msgEl, 'Erro de rede.', 'error');
          });
      });
    });
  }

  function buildIconPicker() {
    if (!pickerEl) return;
    pickerEl.innerHTML = iconCatalog
      .map(function (icon, i) {
        var checked = i === 0 ? ' checked' : '';
        return (
          '<label class="dashboard-category-icon-option" title="' +
          esc(icon.label) +
          '" aria-label="' +
          esc(icon.label) +
          '">' +
          '<input type="radio" name="dashboard_category_icon" value="' +
          esc(icon.key) +
          '"' +
          checked +
          '>' +
          '<span class="vitrine-category-icon vitrine-category-icon--picker">' +
          '<img src="' +
          esc(icon.url || icon.icon_url || '') +
          '" alt="" width="34" height="34" decoding="async" loading="lazy" referrerpolicy="no-referrer">' +
          '</span>' +
          '</label>'
        );
      })
      .join('');

    var first = iconCatalog[0];
    if (iconKeyInput && first) iconKeyInput.value = first.key;

    pickerEl.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (radio.checked && iconKeyInput) iconKeyInput.value = radio.value;
      });
    });
  }

  function openModal() {
    if (!modal) return;
    if (nameInput) nameInput.value = '';
    if (iconKeyInput && iconCatalog[0]) iconKeyInput.value = iconCatalog[0].key;
    var firstRadio = pickerEl && pickerEl.querySelector('input[type="radio"]');
    if (firstRadio) firstRadio.checked = true;
    setMsg(modalMsg, '', '');
    modal.classList.remove('hidden');
    if (nameInput) nameInput.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    setMsg(modalMsg, '', '');
  }

  if (addBtn) addBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = nameInput ? nameInput.value.trim() : '';
      var iconKey = iconKeyInput ? iconKeyInput.value.trim() : '';
      if (!name) {
        setMsg(modalMsg, 'Informe o nome da categoria.', 'error');
        return;
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'A guardar…';
      }
      setMsg(modalMsg, '', '');
      fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/vitrine-categories', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ name: name, icon_key: iconKey })
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar categoria';
          }
          if (res.error) {
            setMsg(modalMsg, res.error, 'error');
            return;
          }
          if (res.category) {
            categories.push(res.category);
            renderList();
          }
          closeModal();
          setMsg(msgEl, 'Categoria adicionada — já está na vitrine.', 'success');
        })
        .catch(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar categoria';
          }
          setMsg(modalMsg, 'Erro de rede.', 'error');
        });
    });
  }

  fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/vitrine-categories', {
    credentials: 'same-origin'
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      if (res.error) {
        if (emptyEl) {
          emptyEl.classList.remove('hidden');
          emptyEl.textContent = res.error;
        }
        return;
      }
      categories = res.categories || [];
      iconCatalog = res.icons || [];
      if (canManage) buildIconPicker();
      renderList();
    })
    .catch(function () {
      if (emptyEl) {
        emptyEl.classList.remove('hidden');
        emptyEl.textContent = 'Não foi possível carregar as categorias.';
      }
    });
})();
