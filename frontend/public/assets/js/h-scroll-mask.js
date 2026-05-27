/**
 * Scroll horizontal sem barra nativa, degradê nas bordas e setas para navegar.
 */
(function () {
  var chevronSvg =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">' +
    '<path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>';

  function scrollStep(track) {
    return Math.max(160, Math.round(track.clientWidth * 0.72));
  }

  function updateMask(mask) {
    var track = mask.querySelector('.h-scroll-mask__track');
    if (!track) return;
    var max = track.scrollWidth - track.clientWidth;
    var scrollable = max > 4;
    var atStart = !scrollable || track.scrollLeft <= 4;
    var atEnd = !scrollable || track.scrollLeft >= max - 4;
    mask.classList.toggle('h-scroll-mask--scrollable', scrollable);
    mask.classList.toggle('h-scroll-mask--start', atStart);
    mask.classList.toggle('h-scroll-mask--end', atEnd);
    var prev = mask.querySelector('.h-scroll-mask__btn--prev');
    var next = mask.querySelector('.h-scroll-mask__btn--next');
    if (prev) {
      prev.disabled = atStart || !scrollable;
      prev.setAttribute('aria-hidden', scrollable && !atStart ? 'false' : 'true');
    }
    if (next) {
      next.disabled = atEnd || !scrollable;
      next.setAttribute('aria-hidden', scrollable && !atEnd ? 'false' : 'true');
    }
  }

  function ensureButtons(mask, track) {
    var prev = mask.querySelector('.h-scroll-mask__btn--prev');
    if (!prev) {
      prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'h-scroll-mask__btn h-scroll-mask__btn--prev';
      prev.setAttribute('aria-label', 'Ver categorias anteriores');
      prev.innerHTML = chevronSvg;
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -scrollStep(track), behavior: 'smooth' });
      });
      mask.insertBefore(prev, track);
    }
    var next = mask.querySelector('.h-scroll-mask__btn--next');
    if (!next) {
      next = document.createElement('button');
      next.type = 'button';
      next.className = 'h-scroll-mask__btn h-scroll-mask__btn--next';
      next.setAttribute('aria-label', 'Ver próximas categorias');
      next.innerHTML = chevronSvg;
      next.addEventListener('click', function () {
        track.scrollBy({ left: scrollStep(track), behavior: 'smooth' });
      });
      mask.appendChild(next);
    }
  }

  function bindMask(mask) {
    var track = mask.querySelector('.h-scroll-mask__track');
    if (!track) return;
    ensureButtons(mask, track);
    if (mask._hScrollMaskBound) {
      updateMask(mask);
      return;
    }
    mask._hScrollMaskBound = true;
    track.addEventListener(
      'scroll',
      function () {
        updateMask(mask);
      },
      { passive: true }
    );
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(function () {
        updateMask(mask);
      }).observe(track);
    }
    window.addEventListener('resize', function () {
      updateMask(mask);
    });
    updateMask(mask);
  }

  function initHScrollMask(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = scope.querySelectorAll
      ? scope.querySelectorAll('.h-scroll-mask')
      : document.querySelectorAll('.h-scroll-mask');
    nodes.forEach(bindMask);
  }

  window.initHScrollMask = initHScrollMask;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initHScrollMask();
    });
  } else {
    initHScrollMask();
  }
})();
