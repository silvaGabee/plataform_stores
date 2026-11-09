/** Moderação das avaliações (gerente): responder, remover e restaurar. */
(function () {
  'use strict';
  var root = document.getElementById('reviews-page');
  if (!root || !window.UI) return;
  var slug = encodeURIComponent(root.getAttribute('data-slug'));
  var filtro = 'todas';
  var lista = document.getElementById('reviews-list');

  function stat(rotulo, valor, tipo) {
    return '<div class="ui-stat' + (tipo ? ' ui-stat--' + tipo : '') + '"><span class="ui-stat-label">' + UI.esc(rotulo) + '</span><span class="ui-stat-value">' + UI.esc(valor) + '</span></div>';
  }

  function estrelas(n) {
    n = Number(n) || 0;
    return '<span class="reviews-mod-stars" aria-label="' + n + ' de 5 estrelas">' + '★★★★★'.slice(0, n) + '<span>' + '★★★★★'.slice(n) + '</span></span>';
  }

  function cartao(a) {
    var badge = a.hidden
      ? '<span class="ui-badge ui-badge--muted">Removida da vitrine</span>'
      : (a.reply ? '<span class="ui-badge ui-badge--ok">Respondida</span>' : '<span class="ui-badge ui-badge--warn">Sem resposta</span>');
    return '<article class="reviews-mod-item' + (a.hidden ? ' is-hidden' : '') + '" data-id="' + a.id + '">' +
      '<header>' + estrelas(a.rating) + ' <strong>' + UI.esc(a.product_name) + '</strong> ' + badge +
        '<time>' + UI.esc(UI.dateTime(a.updated_at)) + '</time></header>' +
      '<p class="reviews-mod-author">' + UI.esc(a.customer_name) + ' · ' + UI.esc(a.customer_email) + '</p>' +
      (a.comment ? '<p class="reviews-mod-comment">' + UI.esc(a.comment) + '</p>' : '<p class="reviews-mod-comment text-muted">Sem comentário, só a nota.</p>') +
      '<label class="reviews-mod-reply-label" for="reply-' + a.id + '">Resposta da loja</label>' +
      '<textarea id="reply-' + a.id + '" rows="2" maxlength="1000" placeholder="Agradeça, explique ou resolva. Aparece em público.">' + UI.esc(a.reply || '') + '</textarea>' +
      '<div class="reviews-mod-actions">' +
        '<button type="button" class="btn btn-primary btn-sm" data-acao="responder">' + (a.reply ? 'Atualizar resposta' : 'Responder') + '</button>' +
        (a.hidden
          ? '<button type="button" class="btn btn-secondary btn-sm" data-acao="restaurar">Restaurar na vitrine</button>'
          : '<button type="button" class="btn btn-danger btn-sm" data-acao="remover">Remover da vitrine</button>') +
      '</div>' +
    '</article>';
  }

  function carregar() {
    UI.api('api/loja/' + slug + '/avaliacoes?filtro=' + filtro).then(function (r) {
      if (r.error) { UI.toast(r.error, 'error'); return; }
      var c = r.contagens;
      document.getElementById('reviews-stats').innerHTML =
        stat('Avaliações', String(c.todas)) +
        stat('Sem resposta', String(c.sem_resposta), c.sem_resposta ? 'warn' : 'ok') +
        stat('1 e 2 estrelas', String(c.negativas), c.negativas ? 'warn' : '') +
        stat('Removidas', String(c.removidas));
      var vazio = {
        todas: 'Nenhum cliente avaliou um produto ainda.',
        sem_resposta: 'Todas as avaliações já têm resposta.',
        negativas: 'Nenhuma avaliação de 1 ou 2 estrelas.',
        removidas: 'Nenhuma avaliação removida.'
      };
      lista.innerHTML = r.avaliacoes.length ? r.avaliacoes.map(cartao).join('') : '<p class="ui-empty">' + vazio[filtro] + '</p>';
    });
  }

  lista.addEventListener('click', function (e) {
    var b = e.target.closest('[data-acao]');
    if (!b) return;
    var item = b.closest('[data-id]');
    var id = item.getAttribute('data-id');
    var acao = b.getAttribute('data-acao');
    var url = 'api/loja/' + slug + '/avaliacoes/' + id + '/' + acao;

    var enviar = function (corpo, msg) {
      b.disabled = true;
      UI.api(url, { method: 'POST', body: corpo || {} }).then(function (r) {
        b.disabled = false;
        if (r.error) { UI.toast(r.error, 'error'); return; }
        UI.toast(msg, 'ok');
        carregar();
      });
    };

    if (acao === 'responder') {
      var texto = item.querySelector('textarea').value.trim();
      enviar({ reply: texto }, texto ? 'Resposta publicada.' : 'Resposta apagada.');
    } else if (acao === 'remover') {
      UI.confirm({
        title: 'Remover avaliação da vitrine?',
        message: 'A avaliação e a nota deixam de aparecer e saem da média do produto. Dá para restaurar depois, na aba Removidas.',
        confirm: 'Remover',
        danger: true
      }).then(function (ok) { if (ok) enviar({}, 'Avaliação removida da vitrine.'); });
    } else if (acao === 'restaurar') {
      enviar({}, 'Avaliação de volta à vitrine.');
    }
  });

  root.querySelectorAll('.ui-seg-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      root.querySelectorAll('.ui-seg-btn').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      filtro = b.getAttribute('data-filtro');
      carregar();
    });
  });

  carregar();
})();
