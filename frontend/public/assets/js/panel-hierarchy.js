(function () {
  if (typeof storeSlug === 'undefined') return;
  var readonly = typeof window.panelReadonly !== 'undefined' && window.panelReadonly;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function api(path, opt) {
    var url = (base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path));
    var fetchOpts = {
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin'
    };
    if (opt) {
      if (opt.method != null) fetchOpts.method = opt.method;
      if (opt.body != null) fetchOpts.body = opt.body;
    }
    return fetch(url, fetchOpts).then(function (r) {
      return r.text().then(function (text) {
        var data = {};
        try {
          data = (text && text.trim()) ? JSON.parse(text) : {};
        } catch (e) {
          if (text && text.indexOf('<') === 0) {
            throw new Error('O servidor retornou uma página de erro em vez de JSON. Confirme que está logado como gerente e que a URL do painel está correta. Status: ' + r.status);
          }
          throw new Error(text ? text.substring(0, 120) : 'Resposta inválida do servidor');
        }
        if (!r.ok) throw new Error(data.error || 'Erro ' + r.status);
        return data;
      });
    });
  }
  var currentRoles = [];
  var storeUsers = [];

  function fillUserSelect() {
    var sel = document.getElementById('role-users');
    if (!sel) return;
    sel.innerHTML = '';
    storeUsers.forEach(function (u) {
      var opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = (u.name || '') + (u.email ? ' (' + u.email + ')' : '');
      sel.appendChild(opt);
    });
  }

  function openEditModal(role) {
    var modal = document.getElementById('role-modal');
    if (!modal) return;
    document.getElementById('role-id').value = role.id || '';
    document.getElementById('role-name').value = role.name || '';
    document.getElementById('role-parent').value = role.parent_role_id || '';
    fillParentSelect(role.id);
    var userSel = document.getElementById('role-users');
    if (userSel) {
      var ids = (role.users || []).map(function (u) { return String(u.id); });
      for (var i = 0; i < userSel.options.length; i++) {
        userSel.options[i].selected = ids.indexOf(String(userSel.options[i].value)) !== -1;
      }
    }
    modal.classList.remove('hidden');
  }

  function fillParentSelect(excludeRoleId) {
    var sel = document.getElementById('role-parent');
    if (!sel) return;
    sel.innerHTML = '<option value="">Nenhum</option>';
    var exclude = excludeRoleId != null ? parseInt(excludeRoleId, 10) : null;
    currentRoles.forEach(function (r) {
      if (exclude !== null && parseInt(r.id, 10) === exclude) return;
      sel.innerHTML += '<option value="' + r.id + '">' + (r.name || '') + '</option>';
    });
  }

  function loadRoles() {
    api('/api/loja/' + storeSlug + '/roles').then(function (res) {
      currentRoles = res.roles || [];
      var listEl = document.getElementById('role-list');
      if (!listEl) return;
      var html = currentRoles.map(function (r, i) {
        var actions = readonly ? '' : ' <button type="button" class="btn btn-sm btn-edit-role" data-index="' + i + '">Editar</button> ' +
          '<button type="button" class="btn btn-sm btn-delete-role" data-index="' + i + '" style="background:#dc2626;color:#fff">Excluir</button>';
        return '<div class="card" style="margin-bottom:0.5rem;padding:0.75rem">' + (r.name || '') + actions + '</div>';
      }).join('') || '<p>Nenhum cargo.</p>';
      listEl.innerHTML = html;
      fillParentSelect(null);
    });
  }

  function loadTree() {
    api('/api/loja/' + storeSlug + '/roles/hierarchy').then(function (res) {
      var treeEl = document.getElementById('hierarchy-tree');
      if (!treeEl) return;
      function render(nodes, level) {
        level = level || 0;
        if (!nodes || !nodes.length) return '';
        var ulClass = level === 0 ? 'hierarchy-ul hierarchy-ul-root' : 'hierarchy-ul hierarchy-ul-child';
        return '<ul class="' + ulClass + '">' + nodes.map(function (n) {
          var names = (n.users || []).map(function (u) { return (u.name || '').replace(/</g, '&lt;'); }).filter(Boolean);
          var userLine = names.length ? '<div class="role-users-names">' + names.join(', ') + '</div>' : '';
          var childrenHtml = render(n.children || [], level + 1);
          var hasChildren = (n.children && n.children.length) ? ' hierarchy-node-has-children' : '';
          return '<li class="hierarchy-node' + hasChildren + '"><div class="hierarchy-node-card"><div class="role-name">' + (n.name || '').replace(/</g, '&lt;') + '</div>' + userLine + '</div>' + childrenHtml + '</li>';
        }).join('') + '</ul>';
      }
      treeEl.innerHTML = render(res.hierarchy || []) || '<p class="hierarchy-empty">Nenhum cargo.</p>';
    });
  }

  function init() {
    var listEl = document.getElementById('role-list');
    var btnNew = document.getElementById('btn-new-role');
    var modal = document.getElementById('role-modal');
    var form = document.getElementById('role-form');
    if (!listEl) return;
    if (readonly) {
      if (btnNew) btnNew.style.display = 'none';
      loadRoles();
      loadTree();
      return;
    }
    if (!btnNew || !modal || !form) return;

    api('/api/loja/' + storeSlug + '/users').then(function (res) {
      storeUsers = res.users || [];
      fillUserSelect();
    }).catch(function () { storeUsers = []; fillUserSelect(); });

    listEl.addEventListener('click', function (e) {
      var btnEdit = e.target.closest('.btn-edit-role');
      var btnDel = e.target.closest('.btn-delete-role');
      if (btnEdit) {
        e.preventDefault();
        var i = parseInt(btnEdit.getAttribute('data-index'), 10);
        if (!isNaN(i) && currentRoles[i]) openEditModal(currentRoles[i]);
      } else if (btnDel) {
        e.preventDefault();
        var i = parseInt(btnDel.getAttribute('data-index'), 10);
        if (isNaN(i) || !currentRoles[i]) return;
        var r = currentRoles[i];
        if (!confirm('Excluir o cargo "' + (r.name || '') + '"?')) return;
        api('/api/loja/' + storeSlug + '/roles/' + r.id, { method: 'DELETE' }).then(function (res) {
          if (res.error) { alert(res.error); return; }
          loadRoles();
          loadTree();
        });
      }
    });

    btnNew.addEventListener('click', function () {
      document.getElementById('role-id').value = '';
      if (form) form.reset();
      fillParentSelect(null);
      var userSel = document.getElementById('role-users');
      if (userSel) { userSel.selectedIndex = -1; for (var i = 0; i < userSel.options.length; i++) userSel.options[i].selected = false; }
      fillUserSelect();
      modal.classList.remove('hidden');
    });

    var btnSeed = document.getElementById('btn-seed-example');
    if (btnSeed) {
      btnSeed.addEventListener('click', function () {
        if (!confirm('Criar organograma automático? Serão adicionados: CEO, Comercial, Administrativo, Produção e subcargos. Cargos que já existirem não serão duplicados. Depois você pode editar os nomes.')) return;
        api('/api/loja/' + storeSlug + '/roles/seed-example', { method: 'POST' }).then(function (res) {
          if (res.error) { alert(res.error); return; }
          alert(res.message || 'Organograma de exemplo criado.');
          loadRoles();
          loadTree();
        });
      });
    }

    var closeBtns = modal.querySelectorAll('.close-modal');
    for (var j = 0; j < closeBtns.length; j++) {
      closeBtns[j].addEventListener('click', function () { modal.classList.add('hidden'); });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var id = document.getElementById('role-id').value;
      var parentVal = document.getElementById('role-parent').value;
      var userSel = document.getElementById('role-users');
      var userIds = [];
      if (userSel) {
        for (var i = 0; i < userSel.options.length; i++) {
          if (userSel.options[i].selected && userSel.options[i].value) userIds.push(parseInt(userSel.options[i].value, 10));
        }
      }
      var payload = {
        name: document.getElementById('role-name').value.trim(),
        parent_role_id: (parentVal === '' || parentVal === null) ? null : parentVal,
        user_ids: userIds
      };
      if (!payload.name) { alert('Informe o nome do cargo.'); return; }
      var path = '/api/loja/' + storeSlug + '/roles' + (id ? '/' + id : '');
      api(path, { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) }).then(function (res) {
        modal.classList.add('hidden');
        loadRoles();
        loadTree();
      }).catch(function (err) {
        alert('Erro ao salvar cargo: ' + (err.message || err));
      });
    });

    loadRoles();
    loadTree();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
