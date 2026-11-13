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

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    var a = parts[0].charAt(0);
    var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (a + b).toUpperCase();
  }

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  var ICON_EDIT = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
  var ICON_TRASH = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>';
  var ICON_USERS = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';

  var currentRoles = [];
  var rolesById = {};
  var tree = [];          // nós raiz: { role, children, depth }
  var flatOrder = [];     // nós em ordem hierárquica (pré-ordem)
  var collapsed = {};     // id -> true
  var storeUsers = [];
  var zoom = 1;
  var didAutoFit = false;
  var searchTerm = '';

  /* ---------- Montagem da árvore ---------- */

  function buildTree() {
    rolesById = {};
    currentRoles.forEach(function (r) { rolesById[String(r.id)] = r; });
    var childrenOf = {};
    var roots = [];
    currentRoles.forEach(function (r) {
      var pid = r.parent_role_id != null && r.parent_role_id !== '' ? String(r.parent_role_id) : '';
      if (pid && pid !== String(r.id) && rolesById[pid]) {
        (childrenOf[pid] = childrenOf[pid] || []).push(r);
      } else {
        roots.push(r);
      }
    });
    var visited = {};
    flatOrder = [];
    function make(role, depth) {
      visited[role.id] = true;
      var node = { role: role, depth: depth, children: [] };
      flatOrder.push(node);
      (childrenOf[String(role.id)] || []).forEach(function (c) {
        if (!visited[c.id]) node.children.push(make(c, depth + 1));
      });
      return node;
    }
    tree = roots.map(function (r) { return make(r, 0); });
    // Cargos presos em ciclo (A → B → A) não são alcançados a partir das raízes: viram raízes.
    currentRoles.forEach(function (r) {
      if (!visited[r.id]) tree.push(make(r, 0));
    });
  }

  function countDescendants(node) {
    return node.children.reduce(function (acc, c) { return acc + 1 + countDescendants(c); }, 0);
  }

  function descendantIds(roleId) {
    var out = {};
    var node = null;
    flatOrder.forEach(function (n) { if (String(n.role.id) === String(roleId)) node = n; });
    (function walk(n) {
      if (!n) return;
      n.children.forEach(function (c) { out[String(c.role.id)] = true; walk(c); });
    })(node);
    return out;
  }

  /* ---------- Indicadores ---------- */

  function renderStats() {
    var el = document.getElementById('hierarchy-stats');
    if (!el) return;
    var people = {};
    var vacant = 0;
    var levels = 0;
    flatOrder.forEach(function (n) {
      var users = n.role.users || [];
      if (!users.length) vacant++;
      users.forEach(function (u) { people[u.id] = true; });
      if (n.depth + 1 > levels) levels = n.depth + 1;
    });
    var values = {
      roles: currentRoles.length,
      levels: levels,
      people: Object.keys(people).length,
      vacant: vacant
    };
    Object.keys(values).forEach(function (k) {
      var v = el.querySelector('[data-stat="' + k + '"]');
      if (v) v.textContent = values[k];
    });
    var vacantStat = el.querySelector('[data-stat="vacant"]');
    if (vacantStat) vacantStat.parentNode.classList.toggle('is-warning', vacant > 0);
  }

  /* ---------- Lista de cargos ---------- */

  function matchesSearch(role) {
    if (!searchTerm) return true;
    if (norm(role.name).indexOf(searchTerm) !== -1) return true;
    return (role.users || []).some(function (u) { return norm(u.name).indexOf(searchTerm) !== -1; });
  }

  function renderList() {
    var listEl = document.getElementById('role-list');
    if (!listEl) return;
    if (!currentRoles.length) {
      listEl.innerHTML = '<div class="hierarchy-list-empty">Nenhum cargo cadastrado ainda.' +
        (readonly ? '' : '<br>Crie o primeiro em <strong>Novo cargo</strong> ou use <strong>Gerar exemplo</strong>.') + '</div>';
      return;
    }
    var rows = flatOrder.filter(function (n) { return matchesSearch(n.role); });
    if (!rows.length) {
      listEl.innerHTML = '<div class="hierarchy-list-empty">Nenhum cargo encontrado para a busca.</div>';
      return;
    }
    listEl.innerHTML = rows.map(function (n) {
      var r = n.role;
      var users = r.users || [];
      var parent = r.parent_role_id != null ? rolesById[String(r.parent_role_id)] : null;
      var meta = [];
      meta.push(parent && n.depth > 0 ? 'Responde a ' + esc(parent.name) : 'Topo da hierarquia');
      var peopleTxt = users.length
        ? '<span class="role-row-count">' + ICON_USERS + users.length + '</span>'
        : '<span class="role-row-vacant">Vago</span>';
      var actions = readonly ? '' :
        '<div class="role-row-actions">' +
          '<button type="button" class="role-row-btn btn-edit-role" data-id="' + esc(r.id) + '" title="Editar cargo" aria-label="Editar ' + esc(r.name) + '">' + ICON_EDIT + '</button>' +
          '<button type="button" class="role-row-btn role-row-btn-danger btn-delete-role" data-id="' + esc(r.id) + '" title="Excluir cargo" aria-label="Excluir ' + esc(r.name) + '">' + ICON_TRASH + '</button>' +
        '</div>';
      var indent = searchTerm ? 0 : Math.min(n.depth, 6);
      return '<div class="role-row" data-id="' + esc(r.id) + '" style="--role-indent:' + indent + '">' +
        '<span class="role-row-dot org-depth-' + (n.depth % 5) + '" aria-hidden="true"></span>' +
        '<button type="button" class="role-row-main" data-locate="' + esc(r.id) + '" title="Mostrar no organograma">' +
          '<span class="role-row-name">' + esc(r.name) + '</span>' +
          '<span class="role-row-meta">' + meta.join(' · ') + '</span>' +
        '</button>' +
        peopleTxt +
        actions +
      '</div>';
    }).join('');
  }

  /* ---------- Organograma ---------- */

  function renderPeople(users) {
    // Só <span>: o cartão é um <button> e só aceita conteúdo em linha.
    if (!users.length) return '<span class="org-card-people org-card-vacant">Cargo vago</span>';
    var shown = users.slice(0, 3);
    var rest = users.length - shown.length;
    return '<span class="org-card-people">' + shown.map(function (u) {
      return '<span class="org-person"><span class="org-avatar" aria-hidden="true">' + esc(initials(u.name)) + '</span><span class="org-person-name">' + esc(u.name) + '</span></span>';
    }).join('') + (rest > 0 ? '<span class="org-person org-card-more">+ ' + rest + (rest === 1 ? ' pessoa' : ' pessoas') + '</span>' : '') + '</span>';
  }

  function renderNode(node) {
    var r = node.role;
    var users = r.users || [];
    var hasChildren = node.children.length > 0;
    var isCollapsed = hasChildren && !!collapsed[r.id];
    var allNames = users.map(function (u) { return u.name; }).join(', ');
    var tag = readonly ? 'div' : 'button type="button"';
    var closeTag = readonly ? 'div' : 'button';
    var card =
      '<div class="org-card-wrap">' +
        '<' + tag + ' class="org-card org-depth-' + (node.depth % 5) + (node.depth === 0 ? ' org-card-root' : '') + '" data-id="' + esc(r.id) + '"' +
          (allNames ? ' title="' + esc(r.name + ' — ' + allNames) + '"' : '') + '>' +
          '<span class="org-card-head">' +
            '<span class="org-card-title">' + esc(r.name) + '</span>' +
            '<span class="org-card-badge" title="' + users.length + (users.length === 1 ? ' pessoa' : ' pessoas') + '">' + ICON_USERS + users.length + '</span>' +
          '</span>' +
          renderPeople(users) +
        '</' + closeTag + '>' +
        (hasChildren
          ? '<button type="button" class="org-toggle" data-toggle="' + esc(r.id) + '" aria-expanded="' + (!isCollapsed) + '" title="' + (isCollapsed ? 'Mostrar' : 'Ocultar') + ' subordinados">' +
              (isCollapsed ? '+' + countDescendants(node) : '−') +
            '</button>'
          : '') +
      '</div>';

    var childrenHtml = '';
    if (hasChildren && !isCollapsed) {
      var allLeaves = node.children.every(function (c) { return !c.children.length; });
      if (allLeaves && node.children.length >= 4) {
        // Muitos subordinados diretos sem equipe própria: empilha em duas colunas
        // ao redor de um eixo central, em vez de abrir uma fileira muito larga.
        var total = node.children.length;
        var lastRowStart = total % 2 === 0 ? total - 2 : total - 1;
        childrenHtml = '<ul class="org-stack">' + node.children.map(function (c, i) {
          var cls = (i % 2 === 0 ? 'org-stack-a' : 'org-stack-b') + (i >= lastRowStart ? ' org-stack-last' : '');
          return '<li class="' + cls + '">' + renderNode(c) + '</li>';
        }).join('') + '</ul>';
      } else {
        childrenHtml = '<ul>' + node.children.map(function (c) { return '<li>' + renderNode(c) + '</li>'; }).join('') + '</ul>';
      }
    }
    return card + childrenHtml;
  }

  function renderTree() {
    var treeEl = document.getElementById('hierarchy-tree');
    if (!treeEl) return;
    if (!tree.length) {
      treeEl.innerHTML = '<div class="org-empty"><strong>O organograma aparece aqui.</strong><span>' +
        (readonly ? 'Ainda não há cargos cadastrados.' : 'Cadastre cargos e indique a quem cada um responde.') + '</span></div>';
      applyZoom();
      return;
    }
    treeEl.innerHTML = '<div class="org-tree"><ul class="org-roots">' + tree.map(function (n) {
      return '<li>' + renderNode(n) + '</li>';
    }).join('') + '</ul></div>';
    applySearchHighlight();
    applyZoom();
  }

  function applySearchHighlight() {
    var treeEl = document.getElementById('hierarchy-tree');
    if (!treeEl) return;
    var cards = treeEl.querySelectorAll('.org-card');
    for (var i = 0; i < cards.length; i++) {
      var role = rolesById[cards[i].getAttribute('data-id')];
      var hit = searchTerm && role && matchesSearch(role);
      cards[i].classList.toggle('is-match', !!hit);
      cards[i].classList.toggle('is-dimmed', !!searchTerm && !hit);
    }
  }

  function applyZoom() {
    var treeEl = document.getElementById('hierarchy-tree');
    var label = document.getElementById('org-zoom-value');
    if (treeEl) treeEl.style.zoom = zoom;
    if (label) label.textContent = Math.round(zoom * 100) + '%';
  }

  function setZoom(z) {
    zoom = Math.max(0.4, Math.min(1.6, Math.round(z * 100) / 100));
    applyZoom();
  }

  function fitZoom() {
    var vp = document.getElementById('org-viewport');
    var treeEl = document.getElementById('hierarchy-tree');
    var inner = treeEl && treeEl.querySelector('.org-tree');
    if (!vp || !inner) return;
    treeEl.style.zoom = 1;
    var natural = inner.scrollWidth;
    var cs = getComputedStyle(vp);
    var available = vp.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) - 4;
    setZoom(natural > available ? available / natural : 1);
    vp.scrollLeft = 0;
  }

  function locate(roleId) {
    // Garante que o cargo esteja visível: abre os superiores recolhidos.
    var r = rolesById[String(roleId)];
    var guard = 0;
    var changed = false;
    while (r && r.parent_role_id != null && guard++ < 50) {
      if (collapsed[r.parent_role_id]) { delete collapsed[r.parent_role_id]; changed = true; }
      r = rolesById[String(r.parent_role_id)];
    }
    if (changed) renderTree();
    var card = document.querySelector('#hierarchy-tree .org-card[data-id="' + String(roleId).replace(/"/g, '') + '"]');
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    card.classList.remove('is-pulse');
    void card.offsetWidth;
    card.classList.add('is-pulse');
  }

  /* ---------- Modal ---------- */

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

  function fillParentSelect(excludeRoleId) {
    var sel = document.getElementById('role-parent');
    if (!sel) return;
    // Um cargo não pode responder a si mesmo nem a um dos seus subordinados (criaria um ciclo).
    var blocked = excludeRoleId != null && excludeRoleId !== '' ? descendantIds(excludeRoleId) : {};
    if (excludeRoleId != null && excludeRoleId !== '') blocked[String(excludeRoleId)] = true;
    var html = '<option value="">Nenhum (topo da hierarquia)</option>';
    flatOrder.forEach(function (n) {
      if (blocked[String(n.role.id)]) return;
      var pad = new Array(n.depth + 1).join('   ');
      html += '<option value="' + esc(n.role.id) + '">' + pad + esc(n.role.name) + '</option>';
    });
    sel.innerHTML = html;
  }

  function openModal(role) {
    var modal = document.getElementById('role-modal');
    var form = document.getElementById('role-form');
    if (!modal || !form) return;
    form.reset();
    var title = document.getElementById('role-modal-title');
    if (title) title.textContent = role ? 'Editar cargo' : 'Novo cargo';
    document.getElementById('role-id').value = role ? role.id : '';
    document.getElementById('role-name').value = role ? (role.name || '') : '';
    fillParentSelect(role ? role.id : null);
    document.getElementById('role-parent').value = role && role.parent_role_id != null ? String(role.parent_role_id) : '';
    var userSel = document.getElementById('role-users');
    if (userSel) {
      var ids = role ? (role.users || []).map(function (u) { return String(u.id); }) : [];
      for (var i = 0; i < userSel.options.length; i++) {
        userSel.options[i].selected = ids.indexOf(String(userSel.options[i].value)) !== -1;
      }
    }
    modal.classList.remove('hidden');
    setTimeout(function () { document.getElementById('role-name').focus(); }, 30);
  }

  /* ---------- Carregamento ---------- */

  function loadAll() {
    return api('/api/loja/' + storeSlug + '/roles').then(function (res) {
      currentRoles = res.roles || [];
      buildTree();
      renderStats();
      renderList();
      renderTree();
      if (!didAutoFit && tree.length) {
        // Na primeira carga, reduz o zoom (até 70%) para o organograma caber na largura.
        didAutoFit = true;
        fitZoom();
        if (zoom < 0.7) setZoom(0.7);
      }
    }).catch(function (err) {
      var treeEl = document.getElementById('hierarchy-tree');
      if (treeEl) treeEl.innerHTML = '<div class="org-empty"><strong>Não foi possível carregar os cargos.</strong><span>' + esc(err.message || err) + '</span></div>';
    });
  }

  /* ---------- Eventos ---------- */

  function init() {
    var listEl = document.getElementById('role-list');
    var treeEl = document.getElementById('hierarchy-tree');
    if (!listEl || !treeEl) return;

    var search = document.getElementById('role-search');
    if (search) {
      search.addEventListener('input', function () {
        searchTerm = norm(search.value.trim());
        renderList();
        applySearchHighlight();
      });
    }

    document.querySelectorAll('[data-org-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var a = btn.getAttribute('data-org-action');
        if (a === 'zoom-in') setZoom(zoom + 0.1);
        else if (a === 'zoom-out') setZoom(zoom - 0.1);
        else if (a === 'fit') fitZoom();
        else if (a === 'expand') { collapsed = {}; renderTree(); }
        else if (a === 'collapse') {
          collapsed = {};
          flatOrder.forEach(function (n) { if (n.depth >= 1 && n.children.length) collapsed[n.role.id] = true; });
          renderTree();
        }
      });
    });

    treeEl.addEventListener('click', function (e) {
      var toggle = e.target.closest('[data-toggle]');
      if (toggle) {
        var id = toggle.getAttribute('data-toggle');
        if (collapsed[id]) delete collapsed[id]; else collapsed[id] = true;
        renderTree();
        return;
      }
      if (readonly) return;
      var card = e.target.closest('.org-card');
      if (card) {
        var role = rolesById[card.getAttribute('data-id')];
        if (role) openModal(role);
      }
    });

    listEl.addEventListener('click', function (e) {
      var loc = e.target.closest('[data-locate]');
      var btnEdit = e.target.closest('.btn-edit-role');
      var btnDel = e.target.closest('.btn-delete-role');
      if (btnEdit) {
        var role = rolesById[btnEdit.getAttribute('data-id')];
        if (role) openModal(role);
      } else if (btnDel) {
        var r = rolesById[btnDel.getAttribute('data-id')];
        if (!r) return;
        var subs = descendantIds(r.id);
        var nSubs = Object.keys(subs).length;
        var msg = 'Excluir o cargo "' + (r.name || '') + '"?' +
          (nSubs ? '\n\nEle tem ' + nSubs + (nSubs === 1 ? ' cargo subordinado' : ' cargos subordinados') + '.' : '');
        if (!confirm(msg)) return;
        api('/api/loja/' + storeSlug + '/roles/' + r.id, { method: 'DELETE' }).then(function (res) {
          if (res.error) { alert(res.error); return; }
          loadAll();
        }).catch(function (err) { alert('Erro ao excluir cargo: ' + (err.message || err)); });
      } else if (loc) {
        locate(loc.getAttribute('data-locate'));
      }
    });

    if (readonly) {
      loadAll();
      return;
    }

    var btnNew = document.getElementById('btn-new-role');
    var modal = document.getElementById('role-modal');
    var form = document.getElementById('role-form');
    if (!btnNew || !modal || !form) { loadAll(); return; }

    api('/api/loja/' + storeSlug + '/users').then(function (res) {
      storeUsers = res.users || [];
      fillUserSelect();
    }).catch(function () { storeUsers = []; fillUserSelect(); });

    btnNew.addEventListener('click', function () { openModal(null); });

    var btnSeed = document.getElementById('btn-seed-example');
    if (btnSeed) {
      btnSeed.addEventListener('click', function () {
        if (!confirm('Gerar organograma de exemplo? Serão adicionados: CEO, Comercial, Administrativo, Produção e subcargos. Cargos que já existirem não serão duplicados. Depois você pode editar os nomes.')) return;
        api('/api/loja/' + storeSlug + '/roles/seed-example', { method: 'POST' }).then(function (res) {
          if (res.error) { alert(res.error); return; }
          alert(res.message || 'Organograma de exemplo criado.');
          loadAll();
        }).catch(function (err) { alert('Erro: ' + (err.message || err)); });
      });
    }

    var closeBtns = modal.querySelectorAll('.close-modal');
    for (var j = 0; j < closeBtns.length; j++) {
      closeBtns[j].addEventListener('click', function () { modal.classList.add('hidden'); });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) modal.classList.add('hidden');
    });

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
      api(path, { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) }).then(function () {
        modal.classList.add('hidden');
        loadAll();
      }).catch(function (err) {
        alert('Erro ao salvar cargo: ' + (err.message || err));
      });
    });

    loadAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
