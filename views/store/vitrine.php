<?php $content = ob_start(); ?>
<div class="container store-vitrine">
    <h1><?= htmlspecialchars($store['name']) ?></h1>
    <p class="store-desc"><?= htmlspecialchars($store['category'] ?? 'Loja') ?> — <?= htmlspecialchars($store['city'] ?? '') ?></p>
    <section class="products-grid">
        <?php foreach ($products as $p): ?>
            <?php $firstImg = !empty($p['images'][0]) ? $p['images'][0]['url'] : null; ?>
            <article class="product-card">
                <a href="<?= base_url("loja/{$store['slug']}/produto/{$p['id']}") ?>">
                    <?php if ($firstImg): ?>
                        <div class="product-card-img"><img src="<?= htmlspecialchars($firstImg) ?>" alt="<?= htmlspecialchars($p['name']) ?>"></div>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <p class="price">R$ <?= number_format($p['sale_price'], 2, ',', '.') ?></p>
                    <p class="stock">Em estoque: <?= (int) $p['stock_quantity'] ?></p>
                </a>
                <button type="button" class="btn btn-sm add-to-cart" data-store-id="<?= $store['id'] ?>" data-product-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['sale_price'] ?>">Adicionar ao carrinho</button>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if (empty($products)): ?>
        <p>Nenhum produto disponível no momento.</p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
