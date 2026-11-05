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

  // ------------------------------------------------------------ favoritos
  // Delegado no documento: a vitrine troca os cards por AJAX ao mudar de categoria.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-fav');
    if (!btn) return;
    e.preventDefault();
    if (btn.disabled) return;
    var id = btn.getAttribute('data-product-id');
    btn.disabled = true;
    UI.api('api/loja/' + encodeURIComponent(slug) + '/favoritos/' + id, { method: 'POST', body: {} }).then(function (r) {
      btn.disabled = false;
      if (r._status === 401) {
        UI.toast('Entre na sua conta para salvar favoritos.', 'info');
        setTimeout(function () { window.location.href = UI.base + '/?auth=login'; }, 900);
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
      UI.toast(r.favorito ? 'Salvo nos favoritos.' : 'Removido dos favoritos.', 'ok', 2200);
      // Na página "Meus favoritos", o card desmarcado sai da lista.
      if (!r.favorito && document.querySelector('.store-favorites-page')) {
        var card = btn.closest('.product-card');
        if (card) card.remove();
      }
    });
  });

  // ------------------------------------------------------------ frete (carrinho)
  var ship = document.getElementById('cart-shipping');
  if (ship) {
    var cepInput = document.getElementById('cart-cep');
    var cepBtn = document.getElementById('cart-cep-btn');
    var out = document.getElementById('cart-shipping-result');
    var lembrado = '';
    try { lembrado = localStorage.getItem('vitrine_cep') || ''; } catch (e) {}
    if (lembrado) cepInput.value = lembrado;
    cepInput.addEventListener('input', function () {
      var d = cepInput.value.replace(/\D/g, '').slice(0, 8);
      cepInput.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
    var calcular = function () {
      var cep = cepInput.value.replace(/\D/g, '');
      if (cep.length !== 8) { out.className = 'store-cart-shipping-result is-error'; out.textContent = 'Digite um CEP com 8 dígitos.'; return; }
      try { localStorage.setItem('vitrine_cep', cepInput.value); } catch (e) {}
      cepBtn.disabled = true;
      out.className = 'store-cart-shipping-result';
      out.textContent = 'Calculando…';
      UI.api('api/loja/' + encodeURIComponent(slug) + '/frete?cep=' + cep + '&subtotal=' + ship.getAttribute('data-subtotal')).then(function (r) {
        cepBtn.disabled = false;
        if (r.error) { out.className = 'store-cart-shipping-result is-error'; out.textContent = r.error; return; }
        var f = r.frete;
        if (!f.disponivel) {
          out.className = 'store-cart-shipping-result is-error';
          out.textContent = 'A loja ainda não entrega neste CEP. Você pode retirar na loja.';
          return;
        }
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
