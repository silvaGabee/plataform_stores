(function () {
  if (typeof storeSlug === 'undefined') {
    return;
  }
  var meta = document.querySelector('meta[name="base-url"]');
  var base = meta && meta.getAttribute('content') ? meta.getAttribute('content').replace(/\/$/, '') : '';

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

  function setMsg(text, isError) {
    if (!msgEl) {
      return;
    }
    msgEl.textContent = text || '';
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
        setMsg('Digite Excluir exatamente como mostrado para confirmar.', true);
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
          setMsg(err.message || String(err), true);
        });
    });
  }
})();
