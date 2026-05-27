(function () {
  var page = document.querySelector('.store-vitrine-page[data-store-slug]');
  if (!page) return;

  var slug = page.getAttribute('data-store-slug');
  var dynamic = document.getElementById('store-vitrine-dynamic');
  if (!slug || !dynamic) return;

  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  base = base.replace(/\/$/, '');
  var loading = false;
  var searchInput = document.getElementById('store-vitrine-search');
  var searchClearBtn = document.querySelector('.store-vitrine-search-clear');
  var catalogCountDefault = '';

  function normalizeSearchText(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  /** Termos da busca (espaços extras ignorados). */
  function getSearchTerms(raw) {
    var normalized = normalizeSearchText(raw).trim();
    if (!normalized) return [];
    return normalized.split(/\s+/).filter(Boolean);
  }

  /**
   * Busca flexível: 1–2 letras = trecho no texto ("T" acha Tênis, Time).
   * 3–4 letras = prefixo de palavra ("Nike", "Bra").
   * 5+ letras = palavra inteira ("brasil" não casa com "brasileira").
   */
  function termMatchesBlob(blob, term) {
    if (!term) return true;
    if (term.length <= 2) {
      return blob.indexOf(term) >= 0;
    }
    var idx = 0;
    while (idx < blob.length) {
      var found = blob.indexOf(term, idx);
      if (found === -1) return false;
      var before = found === 0 ? '' : blob.charAt(found - 1);
      var afterPos = found + term.length;
      var after = afterPos >= blob.length ? '' : blob.charAt(afterPos);
      var boundaryBefore = before === '' || !/[a-z0-9]/.test(before);
      var boundaryAfter = after === '' || !/[a-z0-9]/.test(after);
      if (boundaryBefore && boundaryAfter) return true;
      if (boundaryBefore && /[a-z0-9]/.test(after) && term.length <= 4) return true;
      idx = found + 1;
    }
    return false;
  }

  function blobMatchesSearch(blob, terms) {
    if (!terms.length) return true;
    for (var i = 0; i < terms.length; i++) {
      if (!termMatchesBlob(blob, terms[i])) return false;
    }
    return true;
  }

  function getSearchQuery() {
    return searchInput ? String(searchInput.value || '') : '';
  }

  function getSearchQueryDisplay() {
    return getSearchQuery().trim();
  }

  function toggleSearchClear() {
    if (!searchClearBtn) return;
    var has = getSearchQueryDisplay().length > 0;
    searchClearBtn.classList.toggle('hidden', !has);
  }

  function captureCatalogCountDefault() {
    var desc = dynamic.querySelector('.store-vitrine-section-desc');
    var sub = dynamic.querySelector('.store-category-page-sub');
    var el = desc || sub;
    catalogCountDefault = el ? el.textContent : '';
  }

  function restoreCatalogCount() {
    var desc = dynamic.querySelector('.store-vitrine-section-desc');
    var sub = dynamic.querySelector('.store-category-page-sub');
    var el = desc || sub;
    if (el && catalogCountDefault) {
      el.textContent = catalogCountDefault;
    }
  }

  function setNoResultsVisible(noResults, show, queryLabel) {
    if (!noResults) return;
    if (show) {
      noResults.textContent = 'Nenhum produto encontrado para «' + queryLabel + '».';
      noResults.classList.remove('hidden');
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          noResults.classList.add('is-visible');
        });
      });
    } else {
      noResults.classList.remove('is-visible');
      var onEnd = function () {
        noResults.removeEventListener('transitionend', onEnd);
        if (!noResults.classList.contains('is-visible')) {
          noResults.classList.add('hidden');
        }
      };
      noResults.addEventListener('transitionend', onEnd);
      window.setTimeout(function () {
        if (!noResults.classList.contains('is-visible')) {
          noResults.classList.add('hidden');
        }
      }, 320);
    }
  }

  function applyVitrineSearch() {
    var raw = getSearchQuery();
    var terms = getSearchTerms(raw);
    var isFiltering = terms.length > 0;
    toggleSearchClear();
    var grid = dynamic.querySelector('.products-grid');
    var noResults = dynamic.querySelector('.store-vitrine-search-no-results');
    if (!grid) {
      if (noResults) {
        noResults.classList.remove('is-visible');
        noResults.classList.add('hidden');
      }
      restoreCatalogCount();
      return;
    }
    grid.classList.toggle('products-grid--search-active', isFiltering);
    var cards = grid.querySelectorAll('.product-card');
    var total = cards.length;
    var visible = 0;
    cards.forEach(function (card) {
      var blob = normalizeSearchText(card.getAttribute('data-product-search') || '');
      var show = blobMatchesSearch(blob, terms);
      var wasHidden = card.classList.contains('product-card--search-hidden') || card.hidden;
      card.classList.toggle('product-card--search-hidden', !show);
      card.hidden = !show;
      if (show) {
        visible += 1;
        if (isFiltering && wasHidden) {
          card.classList.remove('product-card--search-in');
          void card.offsetWidth;
          card.classList.add('product-card--search-in');
        }
      } else {
        card.classList.remove('product-card--search-in');
      }
    });
    setNoResultsVisible(noResults, isFiltering && visible === 0, getSearchQueryDisplay());
    var desc = dynamic.querySelector('.store-vitrine-section-desc');
    var sub = dynamic.querySelector('.store-category-page-sub');
    var countEl = desc || sub;
    if (countEl && isFiltering) {
      if (visible === 0) {
        countEl.textContent = 'Nenhum resultado';
      } else if (visible === 1) {
        countEl.textContent = '1 produto encontrado';
      } else if (visible < total) {
        countEl.textContent = visible + ' de ' + total + ' produtos';
      } else {
        countEl.textContent = visible + ' produtos encontrados';
      }
    } else if (!isFiltering) {
      restoreCatalogCount();
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyVitrineSearch);
    searchInput.addEventListener('search', applyVitrineSearch);
  }
  if (searchClearBtn) {
    searchClearBtn.addEventListener('click', function () {
      if (!searchInput) return;
      searchInput.value = '';
      searchInput.focus();
      applyVitrineSearch();
    });
  }

  window.storeVitrineSearchApply = applyVitrineSearch;

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function setActiveCategory(categoryId) {
    var id = categoryId ? parseInt(categoryId, 10) : 0;
    page.querySelectorAll('.js-store-category-link').forEach(function (link) {
      var linkId = parseInt(link.getAttribute('data-category-id'), 10) || 0;
      var active = id > 0 && linkId === id;
      link.classList.toggle('store-vitrine-category-link--active', active);
      if (active) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
    page.classList.toggle('store-vitrine-page--category', id > 0);
  }

  function fetchContent(url) {
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error(data.error || 'Erro ao carregar');
        return data;
      });
    });
  }

  function loadCategory(categoryId, pushHistory) {
    if (loading) return;
    var scrollY = window.scrollY || window.pageYOffset || 0;
    var url =
      categoryId && categoryId > 0
        ? base + '/api/loja/' + encodeURIComponent(slug) + '/vitrine-categories/' + categoryId + '/conteudo'
        : base + '/api/loja/' + encodeURIComponent(slug) + '/vitrine-home/conteudo';
    var historyUrl =
      categoryId && categoryId > 0
        ? base + '/loja/' + encodeURIComponent(slug) + '/categoria/' + categoryId
        : base + '/loja/' + encodeURIComponent(slug);

    loading = true;
    dynamic.classList.add('store-vitrine-dynamic--loading');

    fetchContent(url)
      .then(function (res) {
        dynamic.innerHTML = res.html || '';
        if (res.title) document.title = res.title;
        captureCatalogCountDefault();
        applyVitrineSearch();
        setActiveCategory(res.category_id || 0);
        if (pushHistory) {
          history.pushState(
            { vitrineCategory: res.category_id || null, vitrineMode: res.mode || 'home' },
            '',
            historyUrl
          );
        }
        window.scrollTo(0, scrollY);
      })
      .catch(function (err) {
        alert(err.message || 'Não foi possível carregar a categoria.');
      })
      .finally(function () {
        loading = false;
        dynamic.classList.remove('store-vitrine-dynamic--loading');
      });
  }

  page.addEventListener('click', function (e) {
    var catLink = e.target.closest('.js-store-category-link');
    if (catLink) {
      e.preventDefault();
      var catId = parseInt(catLink.getAttribute('data-category-id'), 10);
      if (!catId) return;
      loadCategory(catId, true);
      return;
    }

    var homeBtn = e.target.closest('.js-store-vitrine-home');
    if (homeBtn) {
      e.preventDefault();
      loadCategory(0, true);
    }
  });

  window.addEventListener('popstate', function (e) {
    var state = e.state || {};
    if (state.vitrineCategory) {
      loadCategory(parseInt(state.vitrineCategory, 10), false);
      return;
    }
    if (state.vitrineMode === 'home') {
      loadCategory(0, false);
      return;
    }
    var m = window.location.pathname.match(/\/loja\/[^/]+\/categoria\/(\d+)/);
    if (m) {
      loadCategory(parseInt(m[1], 10), false);
    } else {
      loadCategory(0, false);
    }
  });

  if (!history.state) {
    var pathMatch = window.location.pathname.match(/\/loja\/[^/]+\/categoria\/(\d+)/);
    history.replaceState(
      {
        vitrineCategory: pathMatch ? parseInt(pathMatch[1], 10) : null,
        vitrineMode: pathMatch ? 'category' : 'home',
      },
      '',
      window.location.href
    );
  }

  captureCatalogCountDefault();
  applyVitrineSearch();
})();
