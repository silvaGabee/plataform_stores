<?php
/**
 * 404 dentro da loja (produto, categoria ou pedido que não existe mais).
 *
 * @var array $store
 * @var string $nf_titulo
 * @var string $nf_mensagem
 * @var list<array> $products sugestões (pode vir vazia)
 */
ob_start();
?>
<div class="store-subpage store-not-found">
    <div class="container store-not-found-inner">
        <div class="store-not-found-card">
            <span class="store-not-found-icon" aria-hidden="true"><?= ui_icon('search', 28) ?></span>
            <h1 class="store-not-found-title"><?= htmlspecialchars($nf_titulo) ?></h1>
            <p class="store-not-found-text"><?= htmlspecialchars($nf_mensagem) ?></p>
            <div class="store-not-found-actions">
                <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-primary store-not-found-cta">Ver a vitrine</a>
                <?php if (logged_in()): ?>
                    <a href="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="btn btn-secondary">Meus pedidos</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($products)): ?>
            <section class="product-related store-not-found-related" aria-labelledby="store-not-found-related-title">
                <h2 id="store-not-found-related-title" class="product-section-title">Mais vendidos da loja</h2>
                <div class="products-grid products-grid--v2 product-related-grid">
                    <?php foreach ($products as $p): ?>
                        <?php require __DIR__ . '/_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
