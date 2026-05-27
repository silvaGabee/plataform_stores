<?php
/** @var array $p */
/** @var array $store */
$cover = product_cover_image($p);
$coverUrl = $cover['url'] ?? null;
$variants = $p['variants'] ?? [];
$hasVariants = product_has_variants($p);
$variantGroups = product_has_variants($p) ? product_variants_grouped($p) : [];
$stockTotal = (int) ($p['stock_quantity'] ?? 0);
$productUrl = base_url("loja/{$store['slug']}/produto/{$p['id']}");
$productSearchBlob = mb_strtolower(trim(($p['name'] ?? '') . ' ' . ($p['description'] ?? '')), 'UTF-8');
?>
<article class="product-card product-card--v2" data-product-search="<?= htmlspecialchars($productSearchBlob, ENT_QUOTES, 'UTF-8') ?>">
    <a href="<?= htmlspecialchars($productUrl) ?>" class="product-card-media">
        <div class="product-card-img<?= $coverUrl ? '' : ' product-card-img--placeholder' ?>">
            <?php if ($coverUrl): ?>
                <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" decoding="async">
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
        <?php if ($hasVariants): ?>
            <span class="product-card-badge">+ opções</span>
        <?php endif; ?>
    </a>
    <div class="product-card-content">
        <a href="<?= htmlspecialchars($productUrl) ?>" class="product-card-info">
            <h3 class="product-card-name"><?= htmlspecialchars($p['name']) ?></h3>
            <?php
            $matrixForCard = $p['variants_matrix'] ?? product_variants_rows_to_matrix($variants);
            $matrixColors = ($matrixForCard && !empty($matrixForCard['colors'])) ? $matrixForCard['colors'] : [];
            ?>
            <?php if ($matrixColors !== []): ?>
                <div class="product-card-variants-preview product-card-variants-preview--colors" aria-label="Cores disponíveis">
                    <div class="product-card-variant-group">
                        <span class="product-card-variant-label">Cores</span>
                        <div class="product-card-variant-pills product-card-variant-pills--swatches">
                            <?php foreach ($matrixColors as $colorName):
                                $hex = product_variant_matrix_color_hex($matrixForCard, $colorName);
                                $isLight = in_array($colorName, ['Branco', 'Amarelo'], true);
                                ?>
                                <span class="product-card-color-swatch<?= $isLight ? ' product-card-color-swatch--light' : '' ?>"<?= $hex ? ' style="--swatch:' . htmlspecialchars($hex) . '"' : '' ?> title="<?= htmlspecialchars($colorName) ?>">
                                    <span class="visually-hidden"><?= htmlspecialchars($colorName) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($hasVariants && $variantGroups !== []): ?>
                <div class="product-card-variants-preview" aria-label="Opções disponíveis">
                    <?php foreach ($variantGroups as $group):
                        $items = $group['items'];
                        $maxShow = 5;
                        $shown = array_slice($items, 0, $maxShow);
                        $extra = count($items) - count($shown);
                        ?>
                        <div class="product-card-variant-group">
                            <span class="product-card-variant-label"><?= htmlspecialchars($group['label']) ?></span>
                            <div class="product-card-variant-pills">
                                <?php foreach ($shown as $item): ?>
                                    <span class="product-card-variant-pill"><?= htmlspecialchars((string) ($item['variant_value'] ?? '')) ?></span>
                                <?php endforeach; ?>
                                <?php if ($extra > 0): ?>
                                    <span class="product-card-variant-pill product-card-variant-pill--more">+<?= $extra ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="product-card-price-row">
                <p class="product-card-price">R$ <?= number_format((float) $p['sale_price'], 2, ',', '.') ?></p>
                <?php if ($stockTotal > 0): ?>
                    <span class="product-card-stock-badge"><?= $hasVariants ? $stockTotal . ' un. no total' : 'Em estoque' ?></span>
                <?php else: ?>
                    <span class="product-card-stock-badge product-card-stock-badge--out">Esgotado</span>
                <?php endif; ?>
            </div>
        </a>
        <div class="product-card-actions">
            <?php if ($stockTotal <= 0): ?>
                <span class="btn btn-secondary btn-sm product-card-btn product-card-btn--disabled" aria-disabled="true">Indisponível</span>
            <?php elseif ($hasVariants): ?>
                <a href="<?= htmlspecialchars($productUrl) ?>" class="btn btn-primary btn-sm product-card-btn product-card-btn--options">
                    <span class="product-card-btn-label product-card-btn-label--long">Escolher opção</span>
                    <span class="product-card-btn-label product-card-btn-label--short">Opções</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary btn-sm add-to-cart product-card-btn product-card-cart-btn"
                    data-store-id="<?= (int) $store['id'] ?>"
                    data-product-id="<?= (int) $p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name']) ?>"
                    data-price="<?= htmlspecialchars((string) $p['sale_price']) ?>"
                    data-max="<?= $stockTotal ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12L6 6z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M6 6L5 3H2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="9" cy="20" r="1.25" fill="currentColor"/><circle cx="18" cy="20" r="1.25" fill="currentColor"/></svg>
                    <span>Adicionar</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
