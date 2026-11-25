/**
 * Menu lateral do painel — gaveta no celular e no tablet.
 */
(function () {
  'use strict';

  if (!document.body.classList.contains('panel')) {
    return;
  }

  var toggle = document.getElementById('panel-nav-toggle');
  var backdrop = document.getElementById('panel-sidebar-backdrop');
  var sidebar = document.getElementById('panel-sidebar');
  if (!toggle || !sidebar) {
    return;
  }

  var mq = window.matchMedia('(max-width: 1023px)');

  function isMobileNav() {
    return mq.matches;
  }

  function setOpen(open) {
    if (!isMobileNav()) {
      open = false;
    }
    document.body.classList.toggle('panel-nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Fechar menu do painel' : 'Abrir menu do painel');
    if (backdrop) {
      backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    document.body.style.overflow = open ? 'hidden' : '';
    // Fechado no celular, o menu fica fora da tela mas os links continuavam
    // recebendo o Tab (uns 15 passos invisíveis). `inert` tira do teclado e
    // do leitor de tela; no computador o menu está sempre à vista.
    var escondido = isMobileNav() && !open;
    sidebar.inert = escondido;
    if (escondido) sidebar.setAttribute('aria-hidden', 'true'); else sidebar.removeAttribute('aria-hidden');
  }

  toggle.addEventListener('click', function () {
    setOpen(!document.body.classList.contains('panel-nav-open'));
  });

  if (backdrop) {
    backdrop.addEventListener('click', function () {
      setOpen(false);
    });
  }

  sidebar.querySelectorAll('.panel-nav-link, .panel-store-link').forEach(function (link) {
    link.addEventListener('click', function () {
      setOpen(false);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setOpen(false);
    }
  });

  function onBreakpointChange() {
    // Também reaplica o `inert` ao cruzar a largura (celular ↔ computador).
    setOpen(isMobileNav() && document.body.classList.contains('panel-nav-open'));
  }
  onBreakpointChange();

  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', onBreakpointChange);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(onBreakpointChange);
  }

  window.addEventListener('resize', onBreakpointChange);
})();
