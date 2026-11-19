/**
 * Ajuda dos indicadores do Analyzing BI: o botão "!" ao lado de cada título.
 *
 *  - mouse: abre ao passar por cima e fecha ao sair (com tolerância para
 *    chegar até o texto);
 *  - clique ou toque: fixa aberto; clicar de novo, fora ou no × fecha;
 *  - teclado: Enter/Espaço abre, Esc fecha e devolve o foco ao botão.
 *
 * No celular o texto abre como folha presa ao rodapé (CSS), com fundo escuro.
 */
(function () {
  'use strict';

  var botoes = document.querySelectorAll('.bi-info');
  if (!botoes.length) return;

  var temHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var celular = window.matchMedia ? window.matchMedia('(max-width: 640px)') : { matches: false };
  var aberto = null;      // { btn, pop, fixo }
  var timerAbrir = null;
  var timerFechar = null;

  var fundo = document.createElement('div');
  fundo.className = 'bi-info-backdrop';
  fundo.hidden = true;
  document.body.appendChild(fundo);

  function popDe(btn) {
    return document.getElementById(btn.getAttribute('aria-controls'));
  }

  function posicionar(pop) {
    pop.classList.remove('is-left', 'is-up');
    if (celular.matches) return;
    var r = pop.getBoundingClientRect();
    if (r.right > window.innerWidth - 12) pop.classList.add('is-left');
    if (r.bottom > window.innerHeight - 12 && r.top - r.height - 40 > 0) pop.classList.add('is-up');
  }

  function fechar(devolverFoco) {
    clearTimeout(timerAbrir);
    clearTimeout(timerFechar);
    if (!aberto) return;
    aberto.pop.hidden = true;
    aberto.btn.setAttribute('aria-expanded', 'false');
    aberto.btn.classList.remove('is-open');
    fundo.hidden = true;
    if (devolverFoco) aberto.btn.focus();
    aberto = null;
  }

  function abrir(btn, fixo) {
    clearTimeout(timerFechar);
    var pop = popDe(btn);
    if (!pop) return;
    if (aberto && aberto.btn !== btn) fechar(false);
    pop.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.classList.add('is-open');
    aberto = { btn: btn, pop: pop, fixo: !!fixo || (aberto && aberto.btn === btn && aberto.fixo) };
    if (celular.matches) fundo.hidden = false;
    posicionar(pop);
  }

  Array.prototype.forEach.call(botoes, function (btn) {
    var wrap = btn.closest('.bi-info-wrap');
    var pop = popDe(btn);

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (aberto && aberto.btn === btn && aberto.fixo) { fechar(false); return; }
      abrir(btn, true);
      // Sem mouse (toque, teclado), o foco vai para o texto: o leitor de tela lê em seguida.
      if (!temHover && pop) pop.focus({ preventScroll: true });
    });

    if (temHover && wrap) {
      wrap.addEventListener('mouseenter', function () {
        clearTimeout(timerFechar);
        if (aberto && aberto.btn === btn) return;
        timerAbrir = setTimeout(function () { abrir(btn, false); }, 120);
      });
      wrap.addEventListener('mouseleave', function () {
        clearTimeout(timerAbrir);
        if (!aberto || aberto.btn !== btn || aberto.fixo) return;
        timerFechar = setTimeout(function () { fechar(false); }, 180);
      });
    }

    if (pop) {
      pop.setAttribute('tabindex', '-1');
      pop.addEventListener('click', function (e) { e.stopPropagation(); });
      var x = pop.querySelector('.bi-info-close');
      if (x) x.addEventListener('click', function () { fechar(true); });
    }
  });

  document.addEventListener('click', function () { if (aberto) fechar(false); });
  fundo.addEventListener('click', function () { fechar(true); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && aberto) {
      e.preventDefault();
      fechar(true);
    }
  });
  window.addEventListener('resize', function () { if (aberto) posicionar(aberto.pop); });
})();
