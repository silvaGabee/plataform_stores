(function () {
  if (typeof storeSlug === 'undefined') return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path, opt) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    return fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opt }).then(function (r) { return r.json(); });
  }
  var currentUsers = [];
  var currentRoles = [];

  function fillRoleSelect() {
    var sel = document.getElementById('user-role');
    if (!sel) return;
    sel.innerHTML = '';
    if (currentRoles.length === 0) {
      sel.innerHTML = '<option value="">Sem cargos cadastrados</option>';
      return;
    }
    sel.innerHTML = '<option value="">Nenhum</option>';
    currentRoles.forEach(function (r) {
      sel.innerHTML += '<option value="' + r.id + '">' + (r.name || '') + '</option>';
    });
  }

  function openEditModal(u) {
    var modal = document.getElementById('user-modal');
    if (!modal) return;
    document.getElementById('user-id').value = u.id || '';
    document.getElementById('user-name').value = u.name || '';
    document.getElementById('user-email').value = u.email || '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-type').value = u.user_type || 'funcionario';
    var roleSel = document.getElementById('user-role');
    if (roleSel) roleSel.value = '';
    var btnDelete = document.getElementById('btn-delete-user');
    if (btnDelete) btnDelete.style.display = u.id ? 'inline-block' : 'none';
    if (u.id) {
      api('/api/loja/' + storeSlug + '/users/' + u.id + '/roles').then(function (res) {
        var roles = res.roles || [];
        if (roles[0] && roleSel) roleSel.value = roles[0].id;
      });
    }
    modal.classList.remove('hidden');
  }

  function load() {
    api('/api/loja/' + storeSlug + '/users').then(function (res) {
      currentUsers = res.users || [];
      var listEl = document.getElementById('user-list');
      if (!listEl) return;
      var html = currentUsers.map(function (u, i) {
        var cargoOuTipo = u.cargo || u.user_type || '';
        return '<div class="card" style="margin-bottom:0.5rem;padding:0.75rem">' +
          (u.name || '') + ' — ' + (u.email || '') + (cargoOuTipo ? ' (' + cargoOuTipo + ')' : '') +
          ' <button type="button" class="btn btn-sm btn-edit-user" data-index="' + i + '">Editar</button></div>';
      }).join('') || '<p>Nenhum funcionário.</p>';
      listEl.innerHTML = html;
    });
  }

  function init() {
    var listEl = document.getElementById('user-list');
    var btnNew = document.getElementById('btn-new-user');
    var modal = document.getElementById('user-modal');
    var form = document.getElementById('user-form');
    if (!listEl || !btnNew || !modal || !form) return;

    listEl.addEventListener('click', function (e) {
      var btnEdit = e.target.closest('.btn-edit-user');
      if (btnEdit) {
        e.preventDefault();
        var i = parseInt(btnEdit.getAttribute('data-index'), 10);
        if (!isNaN(i) && currentUsers[i]) openEditModal(currentUsers[i]);
      }
    });

    btnNew.addEventListener('click', function () {
      document.getElementById('user-id').value = '';
      if (form) form.reset();
      fillRoleSelect();
      var btnDelete = document.getElementById('btn-delete-user');
      if (btnDelete) btnDelete.style.display = 'none';
      modal.classList.remove('hidden');
    });

    var btnDeleteUser = document.getElementById('btn-delete-user');
    if (btnDeleteUser) {
      btnDeleteUser.addEventListener('click', function () {
        var idEl = document.getElementById('user-id');
        var id = idEl ? String(idEl.value || '').trim() : '';
        var nameEl = document.getElementById('user-name');
        var name = nameEl ? nameEl.value : '';
        var idNum = parseInt(id, 10);
        if (!id || isNaN(idNum) || idNum <= 0) {
          alert('Selecione um funcionário para excluir (clique em Editar no funcionário).');
          return;
        }
        if (!confirm('Excluir o funcionário "' + name + '"? Esta ação não pode ser desfeita.')) return;
        var url = (base.replace(/\/$/, '') || '') + '/api/loja/' + storeSlug + '/users/delete';
        fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: idNum })
        }).then(function (r) {
          return r.text().then(function (text) {
            var data = null;
            try { data = text ? JSON.parse(text) : {}; } catch (e) {}
            if (!r.ok) throw new Error((data && data.error) ? data.error : (text || 'Erro ' + r.status));
            return data;
          });
        }).then(function (res) {
          if (res && res.error) { alert(res.error); return; }
          modal.classList.add('hidden');
          load();
        }).catch(function (err) {
          alert('Erro ao excluir: ' + (err.message || err));
        });
      });
    }

    var closeBtns = modal.querySelectorAll('.close-modal');
    for (var j = 0; j < closeBtns.length; j++) {
      closeBtns[j].addEventListener('click', function () { modal.classList.add('hidden'); });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('user-id').value;
      var payload = {
        name: document.getElementById('user-name').value,
        email: document.getElementById('user-email').value,
        user_type: document.getElementById('user-type').value
      };
      if (document.getElementById('user-password').value) payload.password = document.getElementById('user-password').value;
      var path = '/api/loja/' + storeSlug + '/users' + (id ? '/' + id : '');
      var roleId = document.getElementById('user-role').value;
      api(path, { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) }).then(function (res) {
        if (res.error) { alert(res.error); return; }
        var userId = (res.user && res.user.id) ? res.user.id : id;
        var roleIds = roleId ? [parseInt(roleId, 10)] : [];
        if (userId && (roleIds.length > 0 || currentRoles.length > 0)) {
          api('/api/loja/' + storeSlug + '/users/' + userId + '/roles', {
            method: 'POST',
            body: JSON.stringify({ role_ids: roleIds })
          }).then(function () {
            modal.classList.add('hidden');
            load();
          });
        } else {
          modal.classList.add('hidden');
          load();
        }
      });
    });

    api('/api/loja/' + storeSlug + '/roles').then(function (res) {
      currentRoles = res.roles || [];
      fillRoleSelect();
    });
    load();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
