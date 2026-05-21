<?php $content = ob_start(); ?>
<div class="panel-content panel-products-page">
    <header class="panel-page-header panel-products-header">
        <div class="panel-products-header-text">
            <p class="panel-page-eyebrow">Catálogo</p>
            <h1>Produtos</h1>
            <p class="panel-lead text-muted">Gerencie o catálogo da vitrine: nome, categoria, fotos, preços e estoque.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btn-new-product">
            <?= btn_icon_plus() ?>
            Novo produto
        </button>
    </header>

    <div id="product-list" class="panel-products-list" aria-live="polite"></div>

    <div id="product-modal" class="modal modal--product hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-content modal-content--product">
            <header class="product-modal-head">
                <div class="product-modal-head-text">
                    <p class="product-modal-eyebrow">Catálogo da loja</p>
                    <h2 id="modal-title">Novo produto</h2>
                </div>
                <button type="button" class="product-modal-close close-modal" aria-label="Fechar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </header>

            <form id="product-form" class="product-modal-form panel-form-stack">
                <input type="hidden" id="product-id">

                <section class="product-modal-section">
                    <h3 class="product-modal-section-title">Informações</h3>
                    <div class="product-modal-fields">
                        <div class="panel-config-field product-modal-field--full">
                            <label for="product-name">Nome <span class="product-modal-required">*</span></label>
                            <input type="text" id="product-name" required placeholder="Ex.: Camiseta Dry Fit">
                        </div>
                        <div class="panel-config-field product-modal-field--full">
                            <label for="product-description">Descrição</label>
                            <textarea id="product-description" rows="3" placeholder="Detalhes, tamanhos, material…"></textarea>
                        </div>
                        <div class="panel-config-field product-modal-field--full">
                            <label for="product-vitrine-category">Categoria da vitrine</label>
                            <select id="product-vitrine-category" name="vitrine_category_id">
                                <option value="">Sem categoria</option>
                            </select>
                            <p class="panel-field-hint" id="product-vitrine-category-hint">O produto aparece na página desta categoria na loja.</p>
                        </div>
                    </div>
                </section>

                <section class="product-modal-section">
                    <h3 class="product-modal-section-title">Fotos</h3>
                    <p class="panel-field-hint product-photos-cover-hint">Clique numa foto ou em <strong>Definir capa</strong> para escolher a imagem principal do produto.</p>
                    <div class="product-photos-drop">
                        <input type="file" id="product-photos-input" accept="image/*" multiple class="product-photos-input-hidden">
                        <label for="product-photos-input" class="product-photos-drop-label" id="product-photos-add">
                            <span class="product-photos-drop-icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="8.5" cy="10.5" r="1.5" fill="currentColor"/><path d="M21 15l-5-5-4 4-2-2-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="product-photos-drop-text"><strong>Adicionar fotos</strong><span>JPG, PNG ou WebP — várias de uma vez</span></span>
                        </label>
                        <div class="product-photos-slide" id="product-photos-slide"></div>
                    </div>
                </section>

                <section class="product-modal-section">
                    <h3 class="product-modal-section-title">Preços e estoque</h3>
                    <div class="product-modal-grid-2">
                        <div class="panel-config-field">
                            <label for="product-cost">Preço de custo</label>
                            <div class="input-currency">
                                <span class="input-currency-symbol">R$</span>
                                <input type="text" id="product-cost" inputmode="decimal" placeholder="0,00" autocomplete="off">
                            </div>
                        </div>
                        <div class="panel-config-field">
                            <label for="product-sale">Preço de venda <span class="product-modal-required">*</span></label>
                            <div class="input-currency">
                                <span class="input-currency-symbol">R$</span>
                                <input type="text" id="product-sale" inputmode="decimal" placeholder="0,00" required autocomplete="off">
                            </div>
                        </div>
                        <div class="panel-config-field">
                            <label for="product-stock">Estoque inicial</label>
                            <input type="number" id="product-stock" value="0" min="0" step="1">
                        </div>
                        <div class="panel-config-field">
                            <label for="product-min-stock">Estoque mínimo</label>
                            <input type="number" id="product-min-stock" value="0" min="0" step="1">
                        </div>
                    </div>
                </section>

                <section class="product-modal-section product-modal-section--variants">
                    <div class="product-variants-head">
                        <h3 class="product-modal-section-title">Variações</h3>
                        <p class="panel-field-hint product-variants-hint">Adicione as cores, escolha tamanho ou numeração e informe o estoque de cada combinação.</p>
                    </div>

                    <div class="variant-matrix-block">
                        <div class="variant-matrix-step">
                            <p class="variant-matrix-step-label">Cores do produto</p>
                            <div class="product-variant-value-chips" id="variant-color-chips"></div>
                            <button type="button" class="btn btn-secondary btn-sm variant-matrix-add-color" id="variant-toggle-color-picker">
                                <?= btn_icon_plus() ?>
                                Adicionar cor
                            </button>
                            <div id="variant-color-picker" class="variant-color-picker hidden" aria-hidden="true">
                                <p class="panel-field-hint">Selecione uma ou mais cores</p>
                                <div class="product-variant-value-chips" id="variant-color-picker-chips"></div>
                            </div>
                        </div>

                        <div id="variant-axis-block" class="variant-matrix-step hidden" aria-hidden="true">
                            <p class="variant-matrix-step-label">Usar tamanho ou numeração?</p>
                            <div class="product-variant-type-btns" id="variant-axis-btns"></div>
                        </div>

                        <div id="variant-sizes-block" class="variant-matrix-step hidden" aria-hidden="true">
                            <p class="variant-matrix-step-label" id="variant-sizes-label">Tamanhos / numeração</p>
                            <p class="panel-field-hint">Marque quais opções este produto possui</p>
                            <div class="product-variant-value-chips" id="variant-size-chips"></div>
                        </div>

                        <div id="variant-stock-block" class="variant-matrix-step hidden" aria-hidden="true">
                            <p class="variant-matrix-step-label">Estoque por cor e <span id="variant-stock-axis-name">tamanho</span></p>
                            <div class="variant-stock-matrix-wrap">
                                <table class="variant-stock-matrix" id="variant-stock-matrix">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="product-modal-footer">
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary product-modal-submit">Salvar produto</button>
                </footer>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script>
<script>window.productVariantCatalog = ' . json_encode(product_variant_type_catalog(), JSON_UNESCAPED_UNICODE) . ';</script>
<script>window.panelProductsReadonly = false;</script>
<script>
(function() {
  window._productNewFiles = [];
  function renderPhotosSlide() {
    var slide = document.getElementById("product-photos-slide");
    if (!slide) return;
    slide.innerHTML = "";
    var list = window._productNewFiles || [];
    for (var i = 0; i < list.length; i++) {
      var file = list[i];
      var wrap = document.createElement("div");
      wrap.className = "photo-item";
      try {
        var url = URL.createObjectURL(file);
        wrap.innerHTML = "<img src=\"" + url + "\" alt=\"\"><button type=\"button\" class=\"photo-remove\" data-idx=\"" + i + "\" title=\"Remover\">×</button>";
      } catch (e) {
        wrap.innerHTML = "<span class=\"photo-fallback\">" + (file.name || "Foto") + "</span><button type=\"button\" class=\"photo-remove\" data-idx=\"" + i + "\" title=\"Remover\">×</button>";
      }
      slide.appendChild(wrap);
    }
    slide.querySelectorAll(".photo-remove").forEach(function(btn) {
      btn.onclick = function() {
        var idx = parseInt(this.getAttribute("data-idx"), 10);
        window._productNewFiles.splice(idx, 1);
        renderPhotosSlide();
      };
    });
  }
  var input = document.getElementById("product-photos-input");
  if (input) {
    input.onchange = function() {
      var files = this.files;
      if (!files || files.length === 0) return;
      for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var type = file.type || "";
        var ok = type.indexOf("image/") === 0 || /\.(jpe?g|png|gif|webp)$/i.test(file.name || "");
        if (ok) window._productNewFiles.push(file);
      }
      this.value = "";
      if (window._renderProductPhotosSlide) window._renderProductPhotosSlide();
      else renderPhotosSlide();
    };
  }
  window._clearProductNewFiles = function() { window._productNewFiles = []; };
})();
</script>
<script src="' . asset('js/panel-products.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
