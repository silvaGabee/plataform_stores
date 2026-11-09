/** Metas: o gerente define e acompanha a loja e a equipe; o funcionário vê só a própria meta. */
(function () {
  'use strict';
  var root = document.getElementById('goals-page');
  if (!root || !window.UI) return;
  var A = function (p) { return 'api/loja/' + root.getAttribute('data-slug') + '/' + p; };
  var periodEl = document.getElementById('goals-period');
  var form = document.getElementById('goals-store-form');
  var historico = null;
  var splitEditado = false;

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

  /** Parte da meta que os funcionários costumam vender, a partir do histórico. */
  function sugerirDivisao() {
    var hint = document.getElementById('goals-split-hint');
    var meta = Number(form.elements.goal_amount.value) || 0;
    if (!historico || historico.percent === null || historico.percent === undefined) {
      hint.textContent = 'Sem vendas nos últimos meses para sugerir um valor.';
      return;
    }
    // Mesma conta de TeamGoal::suggestedSplit: nunca passa da meta.
    var sugestao = Math.min(Math.round(meta * historico.percent), Math.round(meta * 100)) / 100;
    hint.textContent = 'Nos últimos ' + historico.months + ' meses, os funcionários fizeram ' + pctText(historico.percent) +
      ' das vendas da loja. Para esta meta, isso dá ' + UI.money(sugestao) + '.';
    if (!splitEditado) form.elements.distribute_amount.value = sugestao > 0 ? sugestao.toFixed(2) : '';
  }

  function atualizarDivisao() {
    var ligado = form.elements.distribute.checked;
    document.getElementById('goals-split').hidden = !ligado;
    form.elements.distribute_amount.required = ligado;
    if (ligado) sugerirDivisao();
  }

  function renderTeam(d) {
    renderStats(Number(d.store_goal), Number(d.store_sales), d.store_percent, 'Meta da loja');
    var o = d.sales_by_source;
    document.getElementById('goals-sources').textContent = 'De onde veio o vendido: funcionários ' + UI.money(o.employees) +
      ' · gerente ' + UI.money(o.managers) + ' · vitrine e outras ' + UI.money(o.others) + '.';
    historico = d.team_history;
    splitEditado = false;
    form.elements.goal_amount.value = d.store_goal > 0 ? Number(d.store_goal).toFixed(2) : '';
    form.elements.distribute.checked = false;
    form.elements.distribute_amount.value = '';
    atualizarDivisao();

    var rows = document.getElementById('goals-rows');
    var soma = d.employees.reduce(function (s, e) { return s + Number(e.goal_amount); }, 0);
    var sumEl = document.getElementById('goals-team-sum');
    if (!d.employees.length) {
      sumEl.textContent = '';
      rows.innerHTML = '<tr><td colspan="6" class="ui-empty">Esta loja ainda não tem funcionários.</td></tr>';
      return;
    }
    var diferenca = Math.round((Number(d.store_goal) - soma) * 100) / 100;
    var resto = '';
    if (d.store_goal > 0 && diferenca > 0) resto = ' Os outros ' + UI.money(diferenca) + ' da meta da loja ficam para a vitrine e o gerente.';
    if (diferenca < 0) resto = ' Isso passa ' + UI.money(-diferenca) + ' da meta da loja.';
    sumEl.textContent = 'Soma das metas individuais: ' + UI.money(soma) + '.' + resto +
      ' O vendido de cada um conta só as vendas registradas no nome dele.';
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
    form.elements.distribute.addEventListener('change', atualizarDivisao);
    form.elements.goal_amount.addEventListener('input', function () { if (form.elements.distribute.checked) sugerirDivisao(); });
    form.elements.distribute_amount.addEventListener('input', function () { splitEditado = true; });

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var dividir = form.elements.distribute.checked;
      UI.api(A('goals/store'), {
        method: 'POST',
        body: { period: periodEl.value, goal_amount: form.elements.goal_amount.value, distribute_to_employees: dividir, distribute_amount: form.elements.distribute_amount.value }
      }).then(function (r) {
        if (r.error) { UI.toast(r.error, 'error'); return; }
        if (dividir && !r.distributed_to) {
          UI.toast('Meta salva, mas não houve divisão: a meta é zero ou a loja não tem funcionários.', 'info');
        } else {
          UI.toast(dividir ? UI.money(form.elements.distribute_amount.value) + ' divididos entre ' + r.distributed_to + ' funcionário(s).' : 'Meta salva.', 'ok');
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
