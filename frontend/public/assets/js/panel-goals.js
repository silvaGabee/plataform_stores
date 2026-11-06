/** Metas: o gerente define e acompanha a loja e a equipe; o funcionário vê só a própria meta. */
(function () {
  'use strict';
  var root = document.getElementById('goals-page');
  if (!root || !window.UI) return;
  var A = function (p) { return 'api/loja/' + root.getAttribute('data-slug') + '/' + p; };
  var periodEl = document.getElementById('goals-period');
  var form = document.getElementById('goals-store-form');

  function pctText(p) {
    return p === null || p === undefined ? '—' : Number(p).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
  }

  function progress(p) {
    if (p === null || p === undefined) return '<span class="text-muted">Sem meta</span>';
    var largura = Math.max(0, Math.min(100, Number(p)));
    return '<span class="goals-progress"><span class="ui-progress' + (p >= 100 ? ' is-done' : '') + '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + largura + '"><i style="width:' + largura + '%"></i></span>' +
      '<strong>' + pctText(p) + '</strong></span>';
  }

  function stat(label, valor, tipo) {
    return '<div class="ui-stat' + (tipo ? ' ui-stat--' + tipo : '') + '"><span class="ui-stat-label">' + UI.esc(label) + '</span><span class="ui-stat-value">' + UI.esc(valor) + '</span></div>';
  }

  function renderStats(meta, vendido, percent, rotuloMeta) {
    var falta = Math.max(0, meta - vendido);
    document.getElementById('goals-stats').innerHTML =
      stat(rotuloMeta, meta > 0 ? UI.money(meta) : 'Não definida') +
      stat('Vendido no mês', UI.money(vendido), 'info') +
      stat('Atingido', pctText(percent), percent >= 100 ? 'ok' : 'warn') +
      stat('Falta vender', meta > 0 ? UI.money(falta) : '—');
  }

  function renderEmployee(e) {
    if (!e) {
      document.getElementById('goals-stats').innerHTML = '<p class="ui-empty">Nenhuma meta para mostrar.</p>';
      return;
    }
    renderStats(Number(e.goal_amount), Number(e.total_sales), e.percent, 'Sua meta');
  }

  function renderTeam(d) {
    renderStats(Number(d.store_goal), Number(d.store_sales), d.store_percent, 'Meta da loja');
    form.elements.goal_amount.value = d.store_goal > 0 ? Number(d.store_goal).toFixed(2) : '';
    form.elements.distribute.checked = false;

    var rows = document.getElementById('goals-rows');
    var soma = d.employees.reduce(function (s, e) { return s + Number(e.goal_amount); }, 0);
    var sumEl = document.getElementById('goals-team-sum');
    if (!d.employees.length) {
      sumEl.textContent = '';
      rows.innerHTML = '<tr><td colspan="6" class="ui-empty">Esta loja ainda não tem funcionários.</td></tr>';
      return;
    }
    var diferenca = Math.round((Number(d.store_goal) - soma) * 100) / 100;
    sumEl.textContent = 'Soma das metas individuais: ' + UI.money(soma) +
      (d.store_goal > 0 && diferenca !== 0 ? ' (' + UI.money(Math.abs(diferenca)) + (diferenca > 0 ? ' abaixo' : ' acima') + ' da meta da loja)' : '') +
      '. O vendido conta as vendas registradas no nome de cada um.';
    rows.innerHTML = d.employees.map(function (e) {
      return '<tr data-user="' + Number(e.user_id) + '">' +
        '<td data-label="Funcionário"><strong>' + UI.esc(e.name) + '</strong></td>' +
        '<td data-label="Vendas" class="ui-num">' + Number(e.orders_count) + '</td>' +
        '<td data-label="Vendido" class="ui-num">' + UI.money(e.total_sales) + '</td>' +
        '<td data-label="Atingido">' + progress(e.percent) + '</td>' +
        '<td data-label="Meta (R$)"><input class="ui-input-sm goals-input" type="number" step="0.01" min="0" value="' + Number(e.goal_amount).toFixed(2) + '" aria-label="Meta de ' + UI.esc(e.name) + '"></td>' +
        '<td class="ui-actions"><button type="button" class="btn btn-secondary btn-sm goals-save">Salvar</button></td>' +
      '</tr>';
    }).join('');
  }

  function load() {
    return UI.api(A('goals?period=' + encodeURIComponent(periodEl.value))).then(function (d) {
      if (d.error) { UI.toast(d.error, 'error'); return; }
      if (d.can_manage && form) renderTeam(d); else renderEmployee(d.employees[0]);
    });
  }

  periodEl.addEventListener('change', function () { if (periodEl.value) load(); });

  if (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var dividir = form.elements.distribute.checked;
      UI.api(A('goals/store'), {
        method: 'POST',
        body: { period: periodEl.value, goal_amount: form.elements.goal_amount.value, distribute_to_employees: dividir }
      }).then(function (r) {
        if (r.error) { UI.toast(r.error, 'error'); return; }
        if (dividir && !r.distributed_to) {
          UI.toast('Meta salva, mas não houve divisão: a meta é zero ou a loja não tem funcionários.', 'info');
        } else {
          UI.toast(dividir ? 'Meta salva e dividida entre ' + r.distributed_to + ' funcionário(s).' : 'Meta salva.', 'ok');
        }
        load();
      });
    });

    document.getElementById('goals-rows').addEventListener('click', function (ev) {
      var btn = ev.target.closest('.goals-save');
      if (!btn) return;
      var tr = btn.closest('tr');
      UI.api(A('goals/employee'), {
        method: 'POST',
        body: { period: periodEl.value, user_id: Number(tr.getAttribute('data-user')), goal_amount: tr.querySelector('.goals-input').value }
      }).then(function (r) {
        if (r.error) { UI.toast(r.error, 'error'); return; }
        UI.toast('Meta do funcionário salva.', 'ok');
        load();
      });
    });
  }

  load();
})();
