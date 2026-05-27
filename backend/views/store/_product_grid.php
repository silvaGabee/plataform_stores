<?php if (!empty($products)): ?>
    <div class="store-vitrine-catalog-head">
        <?php if (!empty($catalog_title)): ?>
            <h2 class="store-vitrine-section-title"><?= htmlspecialchars($catalog_title) ?></h2>
        <?php endif; ?>
        <?php if (!empty($catalog_desc)): ?>
            <p class="store-vitrine-section-desc"><?= htmlspecialchars($catalog_desc) ?></p>
        <?php endif; ?>
    </div>
    <section class="products-grid products-grid--v2" aria-label="Lista de produtos">
        <?php foreach ($products as $p): ?>
            <?php require __DIR__ . '/_product_card.php'; ?>
        <?php endforeach; ?>
    </section>
    <p class="store-vitrine-search-no-results hidden" role="status" aria-live="polite"></p>
<?php endif; ?>
