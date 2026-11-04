/**
 * Entregas: pagamentos pendentes (gerente) + quadro por etapa.
 *
 * Etapas só avançam e só pedidos pagos entram no quadro — as duas regras são
 * do servidor; aqui o botão "Avançar" já oferece apenas a próxima etapa.
 * Arrastar continua funcionando no computador; no celular usa-se o botão.
 */
(function () {
  'use strict';
  var root = document.getElementById('entregas');
  if (!root || !window.UI) return;
  var slug = root.getAttribute('data-slug');
  var isGerente = root.getAttribute('data-gerente') === '1';
  var A = function (p) { return 'api/loja/' + slug + '/' + p; };

  var FLUXO = {
    retirada: ['solicitado', 'entregue'],
    entrega: ['solicitado', 'empacotando', 'entregue_transportadora', 'em_rota', 'entregue']
  };
  var NOME = {
    solicitado: 'Solicitado', empacotando: 'Empacotando', entregue_transportadora: 'Na transportadora',
    em_rota: 'Em rota', entregue: 'Entregue'
  };
  var orders = [];

  function next(o) {
    var f = FLUXO[o.delivery_type] || FLUXO.retirada;
    var i = f.indexOf(o.delivery_stage || 'solicitado');
    return i >= 0 && i < f.length - 1 ? f[i + 1] : null;
  }

  function card(o) {
    var prox = next(o);
    var label = o.delivery_type === 'retirada' && prox === 'entregue' ? 'Marcar como retirado' : prox ? 'Avançar: ' + NOME[prox] : '';
    return '<article class="ui-kcard" draggable="' + (prox ? 'true' : 'false') + '" data-id="' + o.id + '">' +
      '<header><strong>Pedido #' + o.id + '</strong><span class="ui-kcard-total">' + UI.money(o.total) + '</span></header>' +
      '<p class="ui-kcard-customer">' + UI.esc(o.customer_name || 'Cliente') + '</p>' +
      '<p class="ui-kcard-meta">' + UI.esc(UI.dateTime(o.created_at)) + '</p>' +
      (o.tracking_code ? '<p class="ui-kcard-track">' + UI.esc(o.tracking_code) + '</p>' : '') +
      (prox ? '<button type="button" class="btn btn-secondary btn-sm ui-kcard-next" data-next="' + prox + '">' + UI.esc(label) + '</button>' : '') +
    '</article>';
  }

  function render() {
    root.querySelectorAll('.ui-col').forEach(function (col) {
      var type = col.getAttribute('data-type');
      var stage = col.getAttribute('data-stage');
      var list = orders.filter(function (o) { return o.delivery_type === type && (o.delivery_stage || 'solicitado') === stage; });
      col.querySelector('[data-cards]').innerHTML = list.length ? list.map(card).join('') : '<p class="ui-col-empty">Nenhum pedido</p>';
      var c = col.querySelector('[data-count]');
      if (c) c.textContent = list.length ? String(list.length) : '';
    });
  }

  function load() {
    UI.api(A('orders/entregas')).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      orders = (r.orders || []).map(function (o) {
        o.delivery_type = (o.delivery_type || 'retirada').toLowerCase();
        return o;
      });
      render();
    });
    if (isGerente) loadAwaiting();
  }

  function askTracking() {
    return UI.confirm({
      title: 'Código de rastreio',
      html: '<label class="ui-field"><span>Informe o código para o cliente acompanhar a entrega</span><input id="ui-track" maxlength="60" placeholder="Ex.: BR123456789MG" autocomplete="off"></label>',
      confirm: 'Salvar e avançar',
      returnValues: true
    }).then(function (v) { return v ? String(v['ui-track'] || '').trim() : null; });
  }

  function move(id, stage) {
    var o = orders.find(function (x) { return String(x.id) === String(id); });
    if (!o) return;
    var p = Promise.resolve(null);
    if (stage === 'em_rota') {
      p = askTracking().then(function (code) {
        if (code === null) return false;
        if (!code) { UI.toast('Informe o código de rastreio.', 'error'); return false; }
        return code;
      });
    }
    p.then(function (code) {
      if (code === false) return;
      var body = { stage: stage };
      if (code) body.tracking_code = code;
      UI.api(A('orders/' + id + '/delivery-stage'), { method: 'POST', body: body }).then(function (r) {
        if (r.error) { UI.toast(r.error, 'error'); return; }
        o.delivery_stage = stage;
        if (code) o.tracking_code = code;
        render();
        UI.toast('Pedido #' + id + ': ' + NOME[stage] + '.', 'ok');
      });
    });
  }

  root.addEventListener('click', function (e) {
    var b = e.target.closest('.ui-kcard-next');
    if (b) move(b.closest('[data-id]').getAttribute('data-id'), b.getAttribute('data-next'));
  });

  // Arrastar (desktop): solta só na próxima etapa válida.
  var dragId = null;
  root.addEventListener('dragstart', function (e) {
    var c = e.target.closest('.ui-kcard');
    if (!c) return;
    dragId = c.getAttribute('data-id');
    c.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  root.addEventListener('dragend', function (e) {
    var c = e.target.closest('.ui-kcard');
    if (c) c.classList.remove('is-dragging');
    root.querySelectorAll('.ui-col.is-over').forEach(function (x) { x.classList.remove('is-over'); });
  });
  root.querySelectorAll('.ui-col').forEach(function (col) {
    col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('is-over'); });
    col.addEventListener('dragleave', function () { col.classList.remove('is-over'); });
    col.addEventListener('drop', function (e) {
      e.preventDefault();
      col.classList.remove('is-over');
      var o = orders.find(function (x) { return String(x.id) === String(dragId); });
      if (!o) return;
      if (o.delivery_type !== col.getAttribute('data-type')) { UI.toast('Este pedido é de ' + (o.delivery_type === 'retirada' ? 'retirada' : 'entrega') + '.', 'error'); return; }
      var alvo = col.getAttribute('data-stage');
      var f = FLUXO[o.delivery_type];
      if (f.indexOf(alvo) <= f.indexOf(o.delivery_stage || 'solicitado')) {
        if (alvo !== o.delivery_stage) UI.toast('A entrega não pode voltar para uma etapa anterior.', 'error');
        return;
      }
      move(o.id, alvo);
    });
  });

  // ---------------------------------------------------------------- pagamentos pendentes
  function restante(exp, now) {
    var e = UI.parseDate(exp), n = UI.parseDate(now);
    if (!e || !n) return '';
    var ms = e - n;
    if (ms <= 0) return 'expirando';
    var min = Math.round(ms / 60000);
    if (min < 60) return 'expira em ' + min + ' min';
    var h = Math.floor(min / 60);
    if (h < 48) return 'expira em ' + h + ' h';
    return 'expira em ' + Math.floor(h / 24) + ' dias';
  }

  function loadAwaiting() {
    UI.api(A('orders/aguardando-pagamento')).then(function (r) {
      var box = document.getElementById('ent-await');
      if (!box) return;
      if (r.error) { box.innerHTML = '<p class="ui-empty">' + UI.esc(r.error) + '</p>'; return; }
      var list = r.orders || [];
      document.getElementById('ent-await-count').textContent = String(list.length);
      if (!list.length) { box.innerHTML = '<p class="ui-empty">Nenhum pedido aguardando pagamento.</p>'; return; }
      box.innerHTML = list.map(function (o) {
        var metodo = o.payment_method === 'pix' ? 'PIX' : o.payment_method === 'cartao' ? 'Cartão na retirada' : 'Forma de pagamento não escolhida';
        var confirmLabel = o.payment_method === 'cartao' ? 'Recebi no cartão' : 'Confirmar PIX recebido';
        return '<article class="ui-await" data-id="' + o.id + '" data-payment="' + (o.payment_id || '') + '">' +
          '<div class="ui-await-main">' +
            '<strong>Pedido #' + o.id + ' · ' + UI.esc(o.customer_name || 'Cliente') + '</strong>' +
            '<span class="text-muted">' + UI.esc(metodo) + ' · ' + (o.delivery_type === 'entrega' ? 'entrega' : 'retirada') + ' · ' + UI.esc(UI.dateTime(o.created_at)) + '</span>' +
          '</div>' +
          '<div class="ui-await-value"><strong>' + UI.money(o.payment_amount || o.total) + '</strong><span class="ui-badge ui-badge--warn">' + UI.esc(restante(o.expires_at, r.now)) + '</span></div>' +
          '<div class="ui-await-actions">' +
            '<button type="button" class="btn btn-ghost btn-sm" data-act="cancel">Cancelar pedido</button>' +
            (o.payment_id ? '<button type="button" class="btn btn-primary btn-sm" data-act="confirm">' + confirmLabel + '</button>' : '') +
          '</div>' +
        '</article>';
      }).join('');
    });
  }

  root.addEventListener('click', function (e) {
    var b = e.target.closest('.ui-await [data-act]');
    if (!b) return;
    var row = b.closest('.ui-await');
    var id = row.getAttribute('data-id');
    if (b.getAttribute('data-act') === 'confirm') {
      UI.confirm({ title: 'Confirmar pagamento do pedido #' + id, message: 'Confirme apenas se o valor já entrou na conta da loja. O estoque é baixado e o pedido entra no quadro de entregas.', confirm: 'Confirmar pagamento' }).then(function (ok) {
        if (!ok) return;
        UI.api(A('payments/confirm'), { method: 'POST', body: { payment_id: +row.getAttribute('data-payment') } }).then(function (r) {
          if (r.error) { UI.toast(r.error, 'error'); load(); return; }
          UI.toast('Pagamento do pedido #' + id + ' confirmado.', 'ok');
          load();
        });
      });
    } else {
      UI.confirm({ title: 'Cancelar pedido #' + id, message: 'O pedido é cancelado e as peças reservadas voltam para a venda. Use quando o cliente desistiu ou não pagou.', confirm: 'Cancelar pedido', cancel: 'Voltar', danger: true }).then(function (ok) {
        if (!ok) return;
        UI.api(A('orders/' + id + '/cancel'), { method: 'POST', body: {} }).then(function (r) {
          if (r.error) { UI.toast(r.error, 'error'); return; }
          UI.toast('Pedido #' + id + ' cancelado.', 'ok');
          load();
        });
      });
    }
  });

  document.getElementById('ent-refresh').addEventListener('click', load);
  load();
  setInterval(function () { if (!document.hidden) load(); }, 30000);
})();
