(function () {
  if (typeof storeSlug === 'undefined') return;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path, opt) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    return fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opt }).then(function (r) { return r.json(); });
  }
  var currentUsers = [];
  var currentRoles = [];

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function initialsFromName(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function typeBadgeClass(userType) {
    return userType === 'gerente' ? 'panel-employee-badge panel-employee-badge--gerente' : 'panel-employee-badge panel-employee-badge--staff';
  }

  function typeLabel(userType) {
    if (userType === 'gerente') return 'Gerente';
    return 'Funcionário';
  }

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
    var titleEl = document.getElementById('user-modal-title');
    if (titleEl) titleEl.textContent = 'Editar funcionário';
    document.getElementById('user-id').value = u.id || '';
    document.getElementById('user-name').value = u.name || '';
    document.getElementById('user-email').value = u.email || '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-type').value = u.user_type || 'funcionario';
    var roleSel = document.getElementById('user-role');
    if (roleSel) roleSel.value = '';
    var btnDelete = document.getElementById('btn-delete-user');
    if (btnDelete) btnDelete.hidden = !u.id;
    if (u.id) {
      api('/api/loja/' + storeSlug + '/users/' + u.id + '/roles').then(function (res) {
        var roles = res.roles || [];
        if (roles[0] && roleSel) roleSel.value = roles[0].id;
      });
    }
    modal.classList.remove('hidden');
  }

  function renderLoading(listEl) {
    var sk = '';
    for (var s = 0; s < 3; s++) {
      sk += '<div class="panel-employee-card panel-employee-card--skeleton" aria-hidden="true"><div class="panel-employee-avatar panel-employee-avatar--skeleton"></div><div class="panel-employee-body"><div class="panel-employee-skel-line panel-employee-skel-line--title"></div><div class="panel-employee-skel-line"></div><div class="panel-employee-skel-line panel-employee-skel-line--short"></div></div></div>';
    }
    listEl.innerHTML = '<div class="panel-employees-list-inner panel-employees-list-inner--loading">' + sk + '</div>';
  }

  function load() {
    var listEl = document.getElementById('user-list');
    if (!listEl) return;
    renderLoading(listEl);
    api('/api/loja/' + storeSlug + '/users').then(function (res) {
      if (res && res.error) {
        listEl.innerHTML = '<div class="panel-employees-empty panel-employees-empty--error"><p class="panel-employees-empty-title">Não foi possível carregar</p><p class="panel-employees-empty-text">' + escapeHtml(res.error) + '</p></div>';
        return;
      }
      currentUsers = res.users || [];
      if (currentUsers.length === 0) {
        listEl.innerHTML =
          '<div class="panel-employees-empty">' +
          '<div class="panel-employees-empty-icon" aria-hidden="true">' +
          '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          '</div>' +
          '<p class="panel-employees-empty-title">Nenhum funcionário ainda</p>' +
          '<p class="panel-employees-empty-text">Adicione o primeiro membro da equipe para dividir o operacional do painel com segurança.</p>' +
          '</div>';
        return;
      }
      var html = currentUsers.map(function (u, i) {
        var name = u.name || 'Sem nome';
        var email = u.email || '';
        var cargo = u.cargo ? String(u.cargo) : '';
        var ut = u.user_type || 'funcionario';
        var cargoBlock = cargo
          ? '<span class="panel-employee-cargo">' + escapeHtml(cargo) + '</span>'
          : '<span class="panel-employee-cargo panel-employee-cargo--muted">Sem cargo na hierarquia</span>';
        return (
          '<article class="panel-employee-card" data-index="' + i + '">' +
          '<div class="panel-employee-avatar" aria-hidden="true">' + escapeHtml(initialsFromName(name)) + '</div>' +
          '<div class="panel-employee-body">' +
          '<div class="panel-employee-row">' +
          '<h2 class="panel-employee-name">' + escapeHtml(name) + '</h2>' +
          '<span class="' + typeBadgeClass(ut) + '">' + escapeHtml(typeLabel(ut)) + '</span>' +
          '</div>' +
          '<p class="panel-employee-email">' + escapeHtml(email) + '</p>' +
          '<div class="panel-employee-footer">' +
          cargoBlock +
          '<button type="button" class="btn btn-sm panel-employee-edit btn-edit-user" data-index="' + i + '">Editar</button>' +
          '</div>' +
          '</div>' +
          '</article>'
        );
      }).join('');
      listEl.innerHTML = '<div class="panel-employees-list-inner">' + html + '</div>';
    }).catch(function () {
      listEl.innerHTML = '<div class="panel-employees-empty panel-employees-empty--error"><p class="panel-employees-empty-title">Erro de conexão</p><p class="panel-employees-empty-text">Tente atualizar a página.</p></div>';
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
      var titleEl = document.getElementById('user-modal-title');
      if (titleEl) titleEl.textContent = 'Novo funcionário';
      document.getElementById('user-id').value = '';
      if (form) form.reset();
      fillRoleSelect();
      var btnDelete = document.getElementById('btn-delete-user');
      if (btnDelete) btnDelete.hidden = true;
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
