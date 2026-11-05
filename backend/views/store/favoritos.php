<?php
ob_start();
?>
<div class="container store-vitrine-body store-favorites-page">
    <header class="store-category-page-head">
        <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-category-back">
            <?= ui_icon('arrow-left', 16) ?>
            <span>Voltar à loja</span>
        </a>
        <div class="store-category-page-bar">
            <div class="store-category-page-title-row">
                <span class="store-category-page-icon store-favorites-icon" aria-hidden="true"><?= ui_icon('heart', 22) ?></span>
                <div class="store-category-page-heading">
                    <h1 class="store-category-page-title">Meus favoritos</h1>
                    <p class="store-category-page-sub">
                        <?= empty($products) ? 'Nenhum produto salvo' : (count($products) === 1 ? '1 produto' : count($products) . ' produtos') ?>
                    </p>
                </div>
            </div>
        </div>
    </header>

    <?php if (!empty($products)): ?>
        <section class="products-grid products-grid--v2" aria-label="Produtos favoritos">
            <?php foreach ($products as $p): ?>
                <?php require __DIR__ . '/_product_card.php'; ?>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <?php
        $empty_title = 'Sua lista de favoritos está vazia';
        $empty_desc = 'Toque no coração de um produto para guardá-lo aqui e encontrar depois.';
        $empty_back_url = base_url("loja/{$store['slug']}");
        $empty_back_label = 'Explorar a loja';
        require __DIR__ . '/_vitrine_empty.php';
        ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
