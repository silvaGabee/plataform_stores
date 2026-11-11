<?php
/**
 * Shell da vitrine com navegação de categorias sem recarregar a página.
 * Variáveis: $store, $bannerUrl (opcional), $vitrine_categories, $active_category_id, $dynamic_view, + dados do partial
 *
 * A busca não fica aqui: $store_header_search pede ao layout_store.php que a
 * coloque no cabeçalho, onde o cliente procura por ela em qualquer loja.
 */
$store_header_search = true;
?>
<div class="store-vitrine-page<?= !empty($vitrine_page_category) ? ' store-vitrine-page--category' : '' ?>" data-store-slug="<?= htmlspecialchars($store['slug']) ?>">
    <?php if (!empty($bannerUrl)): ?>
        <section class="store-vitrine-banner-wrap" aria-label="Destaque da loja">
            <div class="container">
                <div class="store-vitrine-banner-frame">
                    <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="<?= htmlspecialchars($store['name']) ?> — destaque" class="store-vitrine-banner-img" width="1920" height="512" decoding="async" fetchpriority="high">
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php require __DIR__ . '/_vitrine_benefits.php'; ?>

    <?php require __DIR__ . '/_vitrine_categories_nav.php'; ?>

    <div id="store-vitrine-dynamic" class="container store-vitrine-body">
        <?php require __DIR__ . '/' . $dynamic_view . '.php'; ?>
    </div>
</div>
