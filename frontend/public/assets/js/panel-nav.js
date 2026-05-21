/**
 * Menu lateral do painel — drawer em telemóvel/tablet.
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
    if (!isMobileNav()) {
      setOpen(false);
    }
  }

  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', onBreakpointChange);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(onBreakpointChange);
  }

  window.addEventListener('resize', onBreakpointChange);
})();
