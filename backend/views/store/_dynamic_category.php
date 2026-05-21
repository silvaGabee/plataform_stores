<header class="store-category-page-head store-category-page-head--v2">
    <button type="button" class="btn btn-secondary btn-sm store-category-back js-store-vitrine-home">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Voltar à página inicial
    </button>
    <div class="store-category-page-title-row">
        <span class="store-vitrine-category-icon store-vitrine-category-icon--storefront store-category-page-icon" aria-hidden="true">
            <img src="<?= htmlspecialchars($category['icon_url']) ?>" alt="" width="40" height="40" decoding="async" referrerpolicy="no-referrer">
        </span>
        <div>
            <h1 class="store-category-page-title"><?= htmlspecialchars($category['name']) ?></h1>
            <p class="store-category-page-sub">
                <?php if (!empty($products)): ?>
                    <?= count($products) === 1 ? '1 produto' : count($products) . ' produtos' ?>
                <?php else: ?>
                    Nenhum produto nesta categoria
                <?php endif; ?>
            </p>
        </div>
    </div>
</header>

<?php if (!empty($products)): ?>
    <?php
    $catalog_title = null;
    $catalog_desc = null;
    require __DIR__ . '/_product_grid.php';
    ?>
<?php else: ?>
    <?php
    $empty_title = 'Sem produtos nesta categoria';
    $empty_desc = 'Ainda não há produtos publicados em ' . ($category['name'] ?? 'esta categoria') . '. Volte em breve ou explore outras categorias.';
    require __DIR__ . '/_vitrine_empty.php';
    ?>
<?php endif; ?>
