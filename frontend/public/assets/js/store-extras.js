/**
 * Vitrine: favoritos (coração nos cards e no produto) e o formulário de
 * avaliação na página do produto.
 */
(function () {
  'use strict';
  if (!window.UI) return;
  var page = document.querySelector('[data-store-slug]');
  var slug = page ? page.getAttribute('data-store-slug') : null;
  if (!slug) {
    var m = window.location.pathname.match(/\/loja\/([^/]+)/);
    slug = m ? decodeURIComponent(m[1]) : null;
  }
  if (!slug) return;

  /** Entrada da loja, voltando para a página atual depois do login. */
  function irParaEntrar(motivo) {
    var atual = window.location.href.slice(UI.base.length + 1).split('#')[0];
    var url = UI.base + '/loja/' + encodeURIComponent(slug) + '/entrar?motivo=' + encodeURIComponent(motivo) +
      (atual.indexOf('loja/') === 0 ? '&volta=' + encodeURIComponent(atual) : '');
    window.location.href = url;
  }

  // ------------------------------------------------------------ favoritos
  // Delegado no documento: a vitrine troca os cards por AJAX ao mudar de categoria.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-fav');
    if (!btn) return;
    e.preventDefault();
    if (btn.disabled) return;
    var id = btn.getAttribute('data-product-id');
    // Visitante: vai direto para a entrada da loja e volta para esta página.
    // Antes a chamada à API era feita mesmo assim, voltava 401 (erro no
    // console) e levava para a página da plataforma.
    if (document.body.getAttribute('data-logged') === '0') {
      irParaEntrar('favoritos');
      return;
    }
    btn.disabled = true;
    UI.api('api/loja/' + encodeURIComponent(slug) + '/favoritos/' + id, { method: 'POST', body: {} }).then(function (r) {
      btn.disabled = false;
      if (r._status === 401) {
        irParaEntrar('favoritos');
        return;
      }
      if (r.error) { UI.toast(r.error, 'error'); return; }
      // O mesmo produto pode aparecer duas vezes (produto + "você também pode gostar").
      document.querySelectorAll('.js-fav[data-product-id="' + id + '"]').forEach(function (b) {
        b.classList.toggle('is-active', r.favorito);
        b.setAttribute('aria-pressed', r.favorito ? 'true' : 'false');
        var label = b.getAttribute('aria-label') || '';
        b.setAttribute('aria-label', label.replace(/^(Adicionar aos|Remover dos) favoritos/, r.favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos'));
      });
      var naPaginaFavoritos = !!document.querySelector('.store-favorites-page');
      // Mesmo aviso do carrinho (antes era outro, escuro e no centro da tela).
      if (window.StoreUI) {
        StoreUI.notify(r.favorito ? 'Salvo nos favoritos' : 'Removido dos favoritos',
          r.favorito && !naPaginaFavoritos ? { href: UI.base + '/loja/' + encodeURIComponent(slug) + '/favoritos', label: 'Ver favoritos' } : null);
      } else {
        UI.toast(r.favorito ? 'Salvo nos favoritos.' : 'Removido dos favoritos.', 'ok', 2200);
      }
      // Na página "Meus favoritos", o card desmarcado sai da lista.
      if (!r.favorito && naPaginaFavoritos) {
        var card = btn.closest('.product-card');
        if (card) card.remove();
        atualizarFavoritos();
      }
    });
  });

  /**
   * Depois de tirar um card de "Meus favoritos": o contador acompanha e, se a
   * lista acabar, aparece o mesmo aviso de lista vazia que o servidor mostra.
   * Antes a página ficava em branco com "1 produto" no topo.
   */
  function atualizarFavoritos() {
    var pagina = document.querySelector('.store-favorites-page');
    if (!pagina) return;
    var grade = pagina.querySelector('.products-grid');
    var n = grade ? grade.querySelectorAll('.product-card').length : 0;
    var sub = pagina.querySelector('.store-category-page-sub');
    if (sub) sub.textContent = n === 0 ? 'Nenhum produto salvo' : (n === 1 ? '1 produto' : n + ' produtos');
    if (n > 0 || !grade) return;
    var vazio = document.createElement('div');
    vazio.className = 'store-vitrine-empty';
    vazio.innerHTML =
      '<div class="store-vitrine-empty-card">' +
        '<div class="store-vitrine-empty-visual" aria-hidden="true"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7.5-4.6-9.6-9.2C1 8.6 2.9 5 6.4 5c2.1 0 3.6 1.2 4.6 2.6C12 6.2 13.5 5 15.6 5 19.1 5 21 8.6 19.6 11.8 17.5 16.4 12 21 12 21z"/></svg></div>' +
        '<h2 class="store-vitrine-empty-title">Sua lista de favoritos está vazia</h2>' +
        '<p class="store-vitrine-empty-desc">Toque no coração de um produto para guardá-lo aqui e encontrar depois.</p>' +
        '<p class="store-vitrine-empty-action"><a href="' + UI.esc(UI.base + '/loja/' + encodeURIComponent(slug)) + '" class="btn btn-primary btn-sm">Explorar a loja</a></p>' +
      '</div>';
    grade.replaceWith(vazio);
  }

  // ------------------------------------------------------------ frete (carrinho)
  var ship = document.getElementById('cart-shipping');
  if (ship) {
    var cepInput = document.getElementById('cart-cep');
    var cepBtn = document.getElementById('cart-cep-btn');
    var out = document.getElementById('cart-shipping-result');
    var lembrado = '';
    try { lembrado = localStorage.getItem('vitrine_cep') || ''; } catch (e) {}
    if (lembrado) cepInput.value = lembrado;
    var subtotal = parseFloat(ship.getAttribute('data-subtotal')) || 0;
    var pixPct = parseFloat(ship.getAttribute('data-pix-pct')) || 0;
    var outFrete = document.getElementById('cart-total-shipping');
    var outTotal = document.getElementById('cart-total-value');
    var outPix = document.getElementById('cart-total-pix');

    /**
     * Subtotal + frete = total. Antes o frete aparecia ao lado e o total
     * continuava sem ele. O desconto do PIX vale só sobre os produtos, como
     * no checkout (OrderPricing::breakdown).
     */
    function mostrarTotais(frete) {
      if (!outTotal) return;
      if (frete === null) {
        if (outFrete) outFrete.textContent = 'Calculado na próxima etapa';
        outTotal.textContent = UI.money(subtotal);
        if (outPix) outPix.innerHTML = 'ou <strong>' + UI.esc(UI.money(subtotal - descontoPix(subtotal))) + '</strong> no PIX';
        return;
      }
      if (outFrete) outFrete.textContent = frete > 0 ? UI.money(frete) : 'Grátis';
      outTotal.textContent = UI.money(subtotal + frete);
      if (outPix) outPix.innerHTML = 'ou <strong>' + UI.esc(UI.money(subtotal - descontoPix(subtotal) + frete)) + '</strong> no PIX';
    }
    function descontoPix(base) {
      if (base <= 0 || pixPct <= 0) return 0;
      return Math.round(Math.round(base * 100) * pixPct / 100) / 100;
    }
    cepInput.addEventListener('input', function () {
      var d = cepInput.value.replace(/\D/g, '').slice(0, 8);
      cepInput.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
    var calcular = function () {
      var cep = cepInput.value.replace(/\D/g, '');
      if (cep.length !== 8) { out.className = 'store-cart-shipping-result is-error'; out.textContent = 'Digite um CEP com 8 dígitos.'; mostrarTotais(null); return; }
      try { localStorage.setItem('vitrine_cep', cepInput.value); } catch (e) {}
      cepBtn.disabled = true;
      out.className = 'store-cart-shipping-result';
      out.textContent = 'Calculando…';
      UI.api('api/loja/' + encodeURIComponent(slug) + '/frete?cep=' + cep + '&subtotal=' + ship.getAttribute('data-subtotal')).then(function (r) {
        cepBtn.disabled = false;
        if (r.error) { out.className = 'store-cart-shipping-result is-error'; out.textContent = r.error; mostrarTotais(null); return; }
        var f = r.frete;
        if (!f.disponivel) {
          out.className = 'store-cart-shipping-result is-error';
          out.textContent = 'A loja ainda não entrega neste CEP. Você pode retirar na loja.';
          mostrarTotais(null);
          return;
        }
        mostrarTotais(Number(f.valor) || 0);
        out.className = 'store-cart-shipping-result is-ok';
        var valor = f.valor > 0 ? UI.money(f.valor) : 'Frete grátis';
        var prazo = f.prazo != null ? ' · chega em até ' + f.prazo + ' dia' + (f.prazo === 1 ? '' : 's') + ' úte' + (f.prazo === 1 ? 'il' : 'is') : '';
        out.innerHTML = '<strong>' + UI.esc(valor) + '</strong>' + UI.esc(prazo) + (f.regra ? ' <span class="text-muted">(' + UI.esc(f.regra) + ')</span>' : '');
      });
    };
    cepBtn.addEventListener('click', calcular);
    cepInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); calcular(); } });
  }

  // ------------------------------------------------------------ avaliação
  var form = document.getElementById('product-review-form');
  if (form) {
    var section = document.getElementById('avaliacoes');
    var productId = section ? section.getAttribute('data-product-id') : null;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var nota = form.querySelector('input[name="rating"]:checked');
      if (!nota) { UI.toast('Escolha de 1 a 5 estrelas.', 'error'); return; }
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      UI.api('api/loja/' + encodeURIComponent(slug) + '/produtos/' + productId + '/avaliacao', {
        method: 'POST',
        body: { rating: parseInt(nota.value, 10), comment: form.comment.value }
      }).then(function (r) {
        btn.disabled = false;
        if (r.error) { UI.toast(r.error, 'error'); return; }
        UI.toast('Obrigado pela avaliação!', 'ok');
        setTimeout(function () { window.location.hash = 'avaliacoes'; window.location.reload(); }, 700);
      });
    });
  }
})();
