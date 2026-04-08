<?php
ob_start();
$cat = trim((string) ($store['category'] ?? ''));
$city = trim((string) ($store['city'] ?? ''));
$phone = trim((string) ($store['phone'] ?? ''));
$metaItems = [];
if ($cat !== '') {
    $metaItems[] = $cat;
}
if ($city !== '') {
    $metaItems[] = $city;
}
if ($phone !== '') {
    $metaItems[] = $phone;
}
?>
<div class="store-vitrine-page">
    <section class="store-vitrine-hero" aria-labelledby="store-vitrine-title">
        <div class="store-vitrine-hero-glow" aria-hidden="true"></div>
        <div class="container store-vitrine-hero-inner">
            <p class="store-vitrine-eyebrow">Loja online</p>
            <h1 id="store-vitrine-title" class="store-vitrine-title"><?= htmlspecialchars($store['name']) ?></h1>
            <?php if ($metaItems !== []): ?>
                <ul class="store-vitrine-meta" aria-label="Informações da loja">
                    <?php foreach ($metaItems as $i => $label): ?>
                        <li class="store-vitrine-meta-item"><?= htmlspecialchars($label) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <div class="container store-vitrine-body">
        <?php if (!empty($products)): ?>
            <div class="store-vitrine-catalog-head">
                <h2 class="store-vitrine-section-title">Catálogo</h2>
                <p class="store-vitrine-section-desc"><?= count($products) ?> <?= count($products) === 1 ? 'produto disponível' : 'produtos disponíveis' ?></p>
            </div>
            <section class="products-grid" aria-label="Lista de produtos">
                <?php foreach ($products as $p): ?>
                    <?php $firstImg = !empty($p['images'][0]) ? $p['images'][0]['url'] : null; ?>
                    <article class="product-card">
                        <a href="<?= base_url("loja/{$store['slug']}/produto/{$p['id']}") ?>" class="product-card-link">
                            <div class="product-card-img<?= $firstImg ? '' : ' product-card-img--placeholder' ?>">
                                <?php if ($firstImg): ?>
                                    <img src="<?= htmlspecialchars($firstImg) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="product-card-img-icon" aria-hidden="true">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" opacity="0.45"/>
                                            <path d="M3.27 6.96L12 12.01L20.73 6.96" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" opacity="0.45"/>
                                            <path d="M12 22.08V12" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" opacity="0.45"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card-body">
                                <h3 class="product-card-name"><?= htmlspecialchars($p['name']) ?></h3>
                                <p class="price">R$ <?= number_format($p['sale_price'], 2, ',', '.') ?></p>
                                <p class="stock">Em estoque: <?= (int) $p['stock_quantity'] ?></p>
                            </div>
                        </a>
                        <div class="product-card-actions">
                            <button type="button" class="btn btn-primary btn-sm add-to-cart product-card-cart-btn" data-store-id="<?= $store['id'] ?>" data-product-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['sale_price'] ?>">Adicionar ao carrinho</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <div class="store-vitrine-empty">
                <div class="store-vitrine-empty-card">
                    <div class="store-vitrine-empty-visual" aria-hidden="true">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <path d="M20 7H4M20 7L18 19H6L4 7M20 7L18.32 3.55C18.14 3.22 17.79 3 17.41 3H6.59C6.21 3 5.86 3.22 5.68 3.55L4 7" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 11V17M15 11V17" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h2 class="store-vitrine-empty-title">Catálogo em preparação</h2>
                    <p class="store-vitrine-empty-desc">Ainda não há produtos publicados nesta vitrine. Volte em breve ou fale com a loja para mais informações.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
