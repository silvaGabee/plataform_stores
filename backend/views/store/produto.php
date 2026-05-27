<?php
$images = $product['images'] ?? [];
$cover = product_cover_image($product);
$variantsMatrix = $product['variants_matrix'] ?? product_variants_rows_to_matrix($product['variants'] ?? []);
$variantGroups = $variantsMatrix ? [] : product_variants_grouped($product);
$hasVariants = $variantsMatrix !== null || $variantGroups !== [];
$hasMatrixVariants = $variantsMatrix !== null && !empty($variantsMatrix['colors']);
$stockTotal = (int) ($product['stock_quantity'] ?? 0);
$salePrice = (float) ($product['sale_price'] ?? 0);
$content = ob_start();
?>
<div class="container container--product-page product-page product-page--v2">
    <nav class="product-breadcrumb" aria-label="Navegação">
        <a href="<?= base_url("loja/{$store['slug']}") ?>">Início</a>
        <?php if (!empty($vitrineCategory)): ?>
            <span class="product-breadcrumb-sep" aria-hidden="true">/</span>
            <a href="<?= base_url("loja/{$store['slug']}/categoria/" . (int) $vitrineCategory['id']) ?>"><?= htmlspecialchars($vitrineCategory['name']) ?></a>
        <?php endif; ?>
        <span class="product-breadcrumb-sep" aria-hidden="true">/</span>
        <span class="product-breadcrumb-current"><?= htmlspecialchars($product['name']) ?></span>
    </nav>

    <article class="product-detail product-detail--v2"
        data-product-id="<?= (int) $product['id'] ?>"
        data-store-id="<?= (int) $store['id'] ?>"
        data-has-variants="<?= $hasVariants ? '1' : '0' ?>"
        data-has-matrix="<?= $hasMatrixVariants ? '1' : '0' ?>"
        data-total-stock="<?= $stockTotal ?>">
        <div class="product-detail-gallery">
            <?php if (empty($images)): ?>
                <div class="product-gallery-grid product-gallery-grid--empty">
                    <div class="product-gallery-placeholder">Sem foto</div>
                </div>
            <?php else: ?>
                <div class="product-gallery-grid" id="product-gallery-grid" role="region" aria-label="Fotos do produto">
                    <?php foreach ($images as $i => $img): ?>
                        <figure class="product-gallery-cell<?= ($cover && (int) ($img['id'] ?? 0) === (int) ($cover['id'] ?? -1)) || ($i === 0 && !$cover) ? ' product-gallery-cell--cover' : '' ?>">
                            <img
                                src="<?= htmlspecialchars($img['url']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?> — foto <?= $i + 1 ?>"
                                loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                decoding="async">
                        </figure>
                    <?php endforeach; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="product-gallery-mobile-ui" id="product-gallery-mobile-ui" aria-hidden="true">
                        <div class="product-gallery-dots" id="product-gallery-dots" role="tablist" aria-label="Ir para foto"></div>
                        <p class="product-gallery-counter" id="product-gallery-counter" aria-live="polite"></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <aside class="product-detail-aside">
            <div class="product-detail-info">
                <h1 class="product-detail-title"><?= htmlspecialchars($product['name']) ?></h1>

                <div class="product-detail-price-block">
                    <p class="product-detail-price">R$ <?= number_format($salePrice, 2, ',', '.') ?></p>
                    <p class="product-detail-price-note">ou em até 3x sem juros no cartão</p>
                </div>

                <p class="product-detail-stock" id="product-stock-label">
                    <?php if ($hasVariants): ?>
                        Selecione as opções para ver a disponibilidade
                    <?php elseif ($stockTotal > 0): ?>
                        <?= $stockTotal ?> unidade<?= $stockTotal === 1 ? '' : 's' ?> em estoque
                    <?php else: ?>
                        Produto indisponível
                    <?php endif; ?>
                </p>

                <?php if ($hasMatrixVariants): ?>
                    <div class="product-variant-selectors product-variant-selectors--matrix" id="product-variant-selectors">
                        <fieldset class="product-variant-fieldset">
                            <legend class="product-variant-legend">Cor</legend>
                            <div class="product-variant-colors" role="group" aria-label="Cor">
                                <?php foreach ($variantsMatrix['colors'] as $color):
                                    $colorTotal = 0;
                                    foreach ($variantsMatrix['sizes'] as $sz) {
                                        $colorTotal += (int) ($variantsMatrix['stock'][$sz][$color] ?? 0);
                                    }
                                    $disabled = $colorTotal <= 0;
                                    $hex = product_variant_matrix_color_hex($variantsMatrix, $color);
                                    $isLightColor = in_array($color, ['Branco', 'Amarelo'], true);
                                    ?>
                                    <button type="button"
                                        class="product-variant-color<?= $disabled ? ' is-disabled' : '' ?>"
                                        data-variant-type="cor"
                                        data-variant-value="<?= htmlspecialchars($color) ?>"
                                        <?= $disabled ? ' disabled' : '' ?>>
                                        <span class="product-variant-color-dot<?= $isLightColor ? ' product-variant-color-dot--light' : '' ?>"<?= $hex ? ' style="--swatch:' . htmlspecialchars($hex) . '"' : '' ?> aria-hidden="true"></span>
                                        <span class="product-variant-color-label"><?= htmlspecialchars($color) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <fieldset class="product-variant-fieldset product-variant-fieldset--size hidden" id="product-variant-size-fieldset" aria-hidden="true">
                            <legend class="product-variant-legend"><?= htmlspecialchars($variantsMatrix['axis_label'] ?? 'Tamanho') ?></legend>
                            <div class="product-variant-grid" id="product-variant-size-options" role="group"></div>
                        </fieldset>
                    </div>
                    <script type="application/json" id="product-variants-matrix-json"><?= json_encode($variantsMatrix, JSON_UNESCAPED_UNICODE) ?></script>
                <?php elseif ($hasVariants): ?>
                    <div class="product-variant-selectors" id="product-variant-selectors">
                        <?php foreach ($variantGroups as $group):
                            $isColor = ($group['type'] ?? '') === 'cor';
                            ?>
                            <fieldset class="product-variant-fieldset">
                                <legend class="product-variant-legend"><?= htmlspecialchars($group['label']) ?></legend>
                                <?php if ($isColor): ?>
                                    <div class="product-variant-colors" role="group" aria-label="<?= htmlspecialchars($group['label']) ?>">
                                        <?php foreach ($group['items'] as $item):
                                            $val = (string) ($item['variant_value'] ?? '');
                                            $stock = (int) ($item['stock_quantity'] ?? 0);
                                            $disabled = $stock <= 0;
                                            $hex = product_variant_color_hex($val);
                                            ?>
                                            <?php $isLightColor = in_array($val, ['Branco', 'Amarelo'], true); ?>
                                            <button type="button"
                                                class="product-variant-color<?= $disabled ? ' is-disabled' : '' ?>"
                                                data-variant-type="cor"
                                                data-variant-value="<?= htmlspecialchars($val) ?>"
                                                data-variant-stock="<?= $stock ?>"
                                                title="<?= htmlspecialchars($val) ?><?= $disabled ? ' — indisponível' : '' ?>"
                                                <?= $disabled ? ' disabled' : '' ?>>
                                                <span class="product-variant-color-dot<?= $isLightColor ? ' product-variant-color-dot--light' : '' ?>"<?= $hex ? ' style="--swatch:' . htmlspecialchars($hex) . '"' : '' ?> aria-hidden="true"></span>
                                                <span class="product-variant-color-label"><?= htmlspecialchars($val) ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="product-variant-grid" role="group" aria-label="<?= htmlspecialchars($group['label']) ?>">
                                        <?php foreach ($group['items'] as $item):
                                            $stock = (int) ($item['stock_quantity'] ?? 0);
                                            $disabled = $stock <= 0;
                                            ?>
                                            <button type="button"
                                                class="product-variant-option<?= $disabled ? ' is-disabled' : '' ?>"
                                                data-variant-type="<?= htmlspecialchars($group['type']) ?>"
                                                data-variant-value="<?= htmlspecialchars((string) ($item['variant_value'] ?? '')) ?>"
                                                data-variant-stock="<?= $stock ?>"
                                                <?= $disabled ? ' disabled' : '' ?>>
                                                <?= htmlspecialchars((string) ($item['variant_value'] ?? '')) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($stockTotal > 0 || $hasVariants): ?>
                    <div class="product-detail-buy" id="product-detail-buy">
                        <div class="product-detail-qty-row">
                            <div class="product-detail-qty-control">
                                <button type="button" class="product-qty-btn" id="qty-minus" aria-label="Diminuir">−</button>
                                <input type="number" id="qty" min="1" max="<?= $hasVariants ? 1 : $stockTotal ?>" value="1" aria-label="Quantidade"<?= $hasVariants ? ' disabled' : '' ?>>
                                <button type="button" class="product-qty-btn" id="qty-plus" aria-label="Aumentar">+</button>
                            </div>
                            <button type="button"
                                class="btn btn-primary product-detail-cart-btn add-to-cart"
                                id="product-add-cart"
                                data-store-id="<?= (int) $store['id'] ?>"
                                data-product-id="<?= (int) $product['id'] ?>"
                                data-name="<?= htmlspecialchars($product['name']) ?>"
                                data-price="<?= htmlspecialchars((string) $product['sale_price']) ?>"
                                data-max="<?= $hasVariants ? 0 : $stockTotal ?>"
                                <?= $hasVariants ? ' disabled' : '' ?>>
                                Adicionar ao carrinho
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="product-detail-unavailable">Este produto está temporariamente indisponível.</p>
                <?php endif; ?>

                <?php if (!empty($product['description'])): ?>
                    <details class="product-detail-details">
                        <summary>Descrição do produto</summary>
                        <div class="product-detail-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
                    </details>
                <?php endif; ?>
            </div>
        </aside>
    </article>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/store-product.js') . '"></script>';
require __DIR__ . '/layout_store.php';
