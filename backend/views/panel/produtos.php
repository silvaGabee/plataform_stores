<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Produtos</h1>
    <button type="button" class="btn btn-primary" id="btn-new-product">Novo produto</button>
    <div id="product-list"></div>
    <div id="product-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="modal-title">Novo produto</h2>
            <form id="product-form">
                <input type="hidden" id="product-id">
                <label>Nome *</label>
                <input type="text" id="product-name" required>
                <label>Descrição</label>
                <textarea id="product-description"></textarea>
                <label>Fotos do produto</label>
                <div class="product-photos-area">
                    <input type="file" id="product-photos-input" accept="image/*" multiple class="product-photos-input-hidden">
                    <label for="product-photos-input" class="btn btn-sm btn-outline" id="product-photos-add">+ Adicionar fotos</label>
                    <div class="product-photos-slide" id="product-photos-slide"></div>
                </div>
                <label>Preço de custo</label>
                <div class="input-currency">
                    <span class="input-currency-symbol">R$</span>
                    <input type="text" id="product-cost" inputmode="decimal" placeholder="0,00" autocomplete="off">
                </div>
                <label>Preço de venda *</label>
                <div class="input-currency">
                    <span class="input-currency-symbol">R$</span>
                    <input type="text" id="product-sale" inputmode="decimal" placeholder="0,00" required autocomplete="off">
                </div>
                <label>Estoque inicial</label>
                <input type="number" id="product-stock" value="0">
                <label>Estoque mínimo</label>
                <input type="number" id="product-min-stock" value="0">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script>
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
