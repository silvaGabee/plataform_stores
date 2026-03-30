<?php
$images = $product['images'] ?? [];
$content = ob_start();
?>
<div class="container product-page">
    <a href="<?= base_url("loja/{$store['slug']}") ?>" class="back">← Voltar</a>
    <article class="product-detail">
        <div class="product-gallery">
            <?php if (empty($images)): ?>
                <div class="product-gallery-placeholder">Sem foto</div>
            <?php elseif (count($images) === 1): ?>
                <img src="<?= htmlspecialchars($images[0]['url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-gallery-img">
            <?php else: ?>
                <div class="product-gallery-carousel" id="product-gallery">
                    <button type="button" class="carousel-btn carousel-prev" aria-label="Foto anterior">‹</button>
                    <div class="carousel-track">
                        <?php foreach ($images as $img): ?>
                            <div class="carousel-slide"><img src="<?= htmlspecialchars($img['url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="carousel-btn carousel-next" aria-label="Próxima foto">›</button>
                    <div class="carousel-dots" id="carousel-dots"></div>
                </div>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <?php if (!empty($product['description'])): ?>
            <p class="description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <?php endif; ?>
        <p class="price">R$ <?= number_format($product['sale_price'], 2, ',', '.') ?></p>
        <p class="stock">Estoque: <?= (int) $product['stock_quantity'] ?></p>
        <?php if ($product['stock_quantity'] > 0): ?>
            <div class="add-form">
                <label for="qty" class="add-form-label">Quantidade</label>
                <input type="number" id="qty" min="1" max="<?= $product['stock_quantity'] ?>" value="1" aria-label="Quantidade">
                <button type="button" class="btn btn-primary add-to-cart" data-store-id="<?= $store['id'] ?>" data-product-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['sale_price'] ?>" data-max="<?= $product['stock_quantity'] ?>">Adicionar ao carrinho</button>
            </div>
        <?php else: ?>
            <p>Indisponível</p>
        <?php endif; ?>
    </article>
</div>
<?php if (count($images) > 1): ?>
<script>
(function(){
  var el = document.getElementById('product-gallery');
  if (!el) return;
  var track = el.querySelector('.carousel-track');
  var slides = el.querySelectorAll('.carousel-slide');
  var dotsCont = document.getElementById('carousel-dots');
  var total = slides.length;
  var idx = 0;
  for (var i = 0; i < total; i++) {
    var d = document.createElement('button');
    d.type = 'button';
    d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
    d.setAttribute('aria-label', 'Foto ' + (i + 1));
    d.setAttribute('data-idx', i);
    dotsCont.appendChild(d);
  }
  dotsCont.querySelectorAll('.carousel-dot').forEach(function(d){ d.addEventListener('click', function(){ go(parseInt(this.getAttribute('data-idx'), 10)); }); });
  function go(i) {
    idx = (i + total) % total;
    track.style.transform = 'translateX(-' + (idx * 100) + '%)';
    dotsCont.querySelectorAll('.carousel-dot').forEach(function(d, k){ d.classList.toggle('active', k === idx); });
  }
  el.querySelector('.carousel-prev').addEventListener('click', function(){ go(idx - 1); });
  el.querySelector('.carousel-next').addEventListener('click', function(){ go(idx + 1); });
  var startX = 0, endX = 0;
  el.addEventListener('touchstart', function(e){ startX = e.touches[0].clientX; }, { passive: true });
  el.addEventListener('touchend', function(e){ endX = e.changedTouches[0].clientX; var d = startX - endX; if (Math.abs(d) > 50) go(d > 0 ? idx + 1 : idx - 1); }, { passive: true });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
