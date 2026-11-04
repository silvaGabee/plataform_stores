<?php if (!empty($products)): ?>
    <div class="store-vitrine-catalog-head">
        <div class="store-vitrine-catalog-head-text">
            <?php if (!empty($catalog_title)): ?>
                <h2 class="store-vitrine-section-title"><?= htmlspecialchars($catalog_title) ?></h2>
            <?php endif; ?>
            <?php if (!empty($catalog_desc)): ?>
                <p class="store-vitrine-section-desc"><?= htmlspecialchars($catalog_desc) ?></p>
            <?php endif; ?>
        </div>
        <label class="ui-sort">
            <span>Ordenar por</span>
            <select class="js-store-sort" aria-label="Ordenar produtos">
                <?php $__ordem = storefront_current_sort(); foreach (storefront_sort_options() as $__k => $__l): ?>
                    <option value="<?= $__k ?>"<?= $__k === $__ordem ? ' selected' : '' ?>><?= htmlspecialchars($__l) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <section class="products-grid products-grid--v2" aria-label="Lista de produtos">
        <?php foreach ($products as $p): ?>
            <?php require __DIR__ . '/_product_card.php'; ?>
        <?php endforeach; ?>
    </section>
    <p class="store-vitrine-search-no-results hidden" role="status" aria-live="polite"></p>
<?php endif; ?>
