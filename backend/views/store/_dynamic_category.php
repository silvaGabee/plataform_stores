<header class="store-category-page-head">
    <button type="button" class="store-category-back js-store-vitrine-home">
        <?= ui_icon('arrow-left', 16) ?>
        <span>Voltar à página inicial</span>
    </button>
    <div class="store-category-page-bar">
        <div class="store-category-page-title-row">
            <span class="store-category-page-icon" aria-hidden="true">
                <img src="<?= htmlspecialchars($category['icon_url']) ?>" alt="" width="28" height="28" decoding="async" referrerpolicy="no-referrer">
            </span>
            <div class="store-category-page-heading">
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
        <?php if (!empty($products)): ?>
            <?php require __DIR__ . '/_sort_select.php'; ?>
        <?php endif; ?>
    </div>
</header>

<?php if (!empty($products)): ?>
    <?php
    $catalog_head_hidden = true;
    require __DIR__ . '/_product_grid.php';
    ?>
<?php else: ?>
    <?php
    $empty_title = 'Sem produtos nesta categoria';
    $empty_desc = 'Ainda não há produtos publicados em ' . ($category['name'] ?? 'esta categoria') . '. Volte em breve ou explore outras categorias.';
    require __DIR__ . '/_vitrine_empty.php';
    ?>
<?php endif; ?>
