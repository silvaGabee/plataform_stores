(function () {
  var page = document.querySelector('.store-vitrine-page[data-store-slug]');
  if (!page) return;

  var slug = page.getAttribute('data-store-slug');
  var dynamic = document.getElementById('store-vitrine-dynamic');
  if (!slug || !dynamic) return;

  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  base = base.replace(/\/$/, '');
  var loading = false;

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
})();
