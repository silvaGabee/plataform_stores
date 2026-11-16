<?php if (!empty($products)): ?>
    <?php // Na página de categoria o título e a ordenação já vêm no cabeçalho dela. ?>
    <?php if (empty($catalog_head_hidden)): ?>
        <div class="store-vitrine-catalog-head">
            <div class="store-vitrine-catalog-head-text">
                <?php if (!empty($catalog_title)): ?>
                    <h2 class="store-vitrine-section-title"><?= htmlspecialchars($catalog_title) ?></h2>
                <?php endif; ?>
                <?php if (!empty($catalog_desc)): ?>
                    <?php // Região viva: a busca reescreve este texto ("3 resultados para…") e o leitor de tela anuncia. ?>
                    <p class="store-vitrine-section-desc" aria-live="polite"><?= htmlspecialchars($catalog_desc) ?></p>
                <?php endif; ?>
            </div>
            <?php require __DIR__ . '/_sort_select.php'; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($catalog_head_hidden)): ?>
        <?php // Na categoria o cabeçalho visível é outro; sem este h2 os títulos pulavam de h1 para h3. ?>
        <h2 class="visually-hidden">Produtos</h2>
    <?php endif; ?>
    <section class="products-grid products-grid--v2" aria-label="Lista de produtos">
        <?php foreach ($products as $p): ?>
            <?php require __DIR__ . '/_product_card.php'; ?>
        <?php endforeach; ?>
    </section>
    <div class="store-vitrine-search-no-results hidden" role="status" aria-live="polite"></div>
<?php endif; ?>
