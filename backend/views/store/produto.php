<?php
$images = $product['images'] ?? [];
$cover = product_cover_image($product);
$variantsMatrix = $product['variants_matrix'] ?? product_variants_rows_to_matrix($product['variants'] ?? []);
$variantGroups = $variantsMatrix ? [] : product_variants_grouped($product);
$hasVariants = $variantsMatrix !== null || $variantGroups !== [];
$hasMatrixVariants = $variantsMatrix !== null && !empty($variantsMatrix['colors']);
$stockTotal = (int) ($product['stock_quantity'] ?? 0);
$salePrice = (float) ($product['sale_price'] ?? 0);
$effectivePrice = product_effective_price($product);
$discountPct = product_discount_percent($product);
$installmentsText = \App\Services\InstallmentPlan::headline($store, $effectivePrice);
$installmentsRules = \App\Services\InstallmentPlan::rules($store);
$pixPrice = \App\Services\OrderPricing::pixPrice($store, $effectivePrice);
$pixPct = \App\Services\OrderPricing::pixPct($store);
$isFavorite = isset(storefront_favorite_ids((int) $store['id'])[(int) $product['id']]);
$productUrlAbs = base_url("loja/{$store['slug']}/produto/{$product['id']}");
$whatsappUrl = store_whatsapp_url($store, 'Olá! Tenho uma dúvida sobre o produto "' . $product['name'] . '": ' . $productUrlAbs);
$reviewSummary = $review_summary ?? ['media' => 0.0, 'total' => 0, 'distribuicao' => []];
$reviews = $reviews ?? [];
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
                <div class="product-gallery-carousel product-gallery-carousel--v2" id="product-gallery-carousel" role="region" aria-label="Fotos do produto">
                    <div class="carousel-track">
                        <?php foreach ($images as $i => $img): ?>
                            <figure class="carousel-slide product-gallery-cell<?= ($cover && (int) ($img['id'] ?? 0) === (int) ($cover['id'] ?? -1)) || ($i === 0 && !$cover) ? ' product-gallery-cell--cover' : '' ?>">
                                <img
                                    src="<?= htmlspecialchars($img['url']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?> — foto <?= $i + 1 ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async">
                            </figure>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <button type="button" class="carousel-btn carousel-prev" id="product-gallery-prev" aria-label="Foto anterior"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
                        <button type="button" class="carousel-btn carousel-next" id="product-gallery-next" aria-label="Próxima foto"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button>
                        <div class="product-gallery-mobile-ui" id="product-gallery-mobile-ui" aria-label="Controle de fotos">
                            <div class="product-gallery-dots" id="product-gallery-dots" role="tablist" aria-label="Ir para foto"></div>
                            <p class="product-gallery-counter" id="product-gallery-counter" aria-live="polite"></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="product-detail-aside">
            <div class="product-detail-info">
                <div class="product-detail-title-row">
                    <h1 class="product-detail-title"><?= htmlspecialchars($product['name']) ?></h1>
                    <button type="button" class="product-fav-btn product-fav-btn--inline js-fav<?= $isFavorite ? ' is-active' : '' ?>" data-product-id="<?= (int) $product['id'] ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>" aria-label="<?= $isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21s-7.5-4.6-9.6-9.2C1 8.6 2.9 5 6.4 5c2.1 0 3.6 1.2 4.6 2.6C12 6.2 13.5 5 15.6 5 19.1 5 21 8.6 19.6 11.8 17.5 16.4 12 21 12 21z"/></svg>
                    </button>
                </div>
                <?php if ($reviewSummary['total'] > 0): ?>
                    <a href="#avaliacoes" class="product-detail-rating" aria-label="Nota <?= number_format($reviewSummary['media'], 1, ',', '') ?> de 5. Ver avaliações">
                        <?= ui_stars($reviewSummary['media'], 16) ?>
                        <strong><?= number_format($reviewSummary['media'], 1, ',', '') ?></strong>
                        <span>(<?= $reviewSummary['total'] ?> avaliaç<?= $reviewSummary['total'] === 1 ? 'ão' : 'ões' ?>)</span>
                    </a>
                <?php endif; ?>

                <div class="product-detail-price-block">
                    <?php if ($discountPct > 0): ?>
                        <p class="product-detail-price-old"><s>R$ <?= number_format($salePrice, 2, ',', '.') ?></s> <span class="product-detail-sale-badge">-<?= $discountPct ?>%</span></p>
                    <?php endif; ?>
                    <p class="product-detail-price">R$ <?= number_format($effectivePrice, 2, ',', '.') ?></p>
                    <?php if ($pixPrice !== null): ?>
                        <p class="product-detail-pix"><?= ui_icon('qr', 15) ?> <strong>R$ <?= number_format($pixPrice, 2, ',', '.') ?></strong> no PIX <span class="product-detail-pix-badge"><?= rtrim(rtrim(number_format($pixPct, 2, ',', ''), '0'), ',') ?>% off</span></p>
                    <?php endif; ?>
                    <?php if ($installmentsText): ?>
                        <p class="product-detail-price-note"><?= htmlspecialchars($installmentsText) ?> no cartão (entrega)</p>
                    <?php elseif ($installmentsRules['max'] > 1): ?>
                        <p class="product-detail-price-note">Parcelamento no cartão disponível no checkout</p>
                    <?php endif; ?>
                    <?php if ($pixPrice === null): ?>
                        <p class="product-detail-price-note product-detail-price-note--pix"><?= ui_icon('qr', 14) ?> PIX com confirmação pela loja</p>
                    <?php endif; ?>
                </div>

                <p class="product-detail-stock" id="product-stock-label">
                    <?php if ($hasVariants): ?>
                        Selecione as opções para ver a disponibilidade
                    <?php elseif ($stockTotal > 5): ?>
                        Em estoque
                    <?php elseif ($stockTotal > 0): ?>
                        Últimas <?= $stockTotal ?> unidade<?= $stockTotal === 1 ? '' : 's' ?>
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
                                data-price="<?= htmlspecialchars((string) $effectivePrice) ?>"
                                data-max="<?= $hasVariants ? 0 : $stockTotal ?>"
                                <?= $hasVariants ? ' disabled' : '' ?>>
                                Adicionar ao carrinho
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="product-detail-unavailable">Este produto está temporariamente indisponível.</p>
                <?php endif; ?>

                <?php if ($whatsappUrl !== null): ?>
                    <a class="product-detail-whatsapp" href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener">
                        <?= ui_icon('whatsapp', 20) ?>
                        <span>Tirar dúvida no WhatsApp</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($product['description'])): ?>
                    <details class="product-detail-details" open>
                        <summary>Descrição do produto</summary>
                        <div class="product-detail-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
                    </details>
                <?php endif; ?>
            </div>
        </aside>
    </article>

    <section class="product-reviews" id="avaliacoes" aria-labelledby="product-reviews-title" data-slug="<?= htmlspecialchars($store['slug']) ?>" data-product-id="<?= (int) $product['id'] ?>">
        <h2 id="product-reviews-title" class="product-section-title">Avaliações</h2>
        <div class="product-reviews-grid">
            <div class="product-reviews-summary">
                <?php if ($reviewSummary['total'] > 0): ?>
                    <p class="product-reviews-score"><?= number_format($reviewSummary['media'], 1, ',', '') ?><span>/5</span></p>
                    <?= ui_stars($reviewSummary['media'], 20) ?>
                    <p class="product-reviews-count"><?= $reviewSummary['total'] ?> avaliaç<?= $reviewSummary['total'] === 1 ? 'ão' : 'ões' ?></p>
                    <ul class="product-reviews-bars" aria-label="Distribuição das notas">
                        <?php foreach ([5, 4, 3, 2, 1] as $n):
                            $qtd = (int) ($reviewSummary['distribuicao'][$n] ?? 0);
                            $pct = $reviewSummary['total'] > 0 ? round($qtd / $reviewSummary['total'] * 100) : 0;
                            ?>
                            <li><span><?= $n ?>★</span><span class="product-reviews-bar"><span style="width: <?= $pct ?>%"></span></span><span><?= $qtd ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="product-reviews-empty">Este produto ainda não tem avaliações.</p>
                <?php endif; ?>

                <?php if (!empty($can_review)): ?>
                    <form class="product-review-form" id="product-review-form">
                        <p class="product-review-form-title"><?= !empty($my_review) ? 'Sua avaliação' : 'Você comprou este produto. O que achou?' ?></p>
                        <div class="product-review-stars" role="radiogroup" aria-label="Sua nota">
                            <?php
                            // Ordem 5→1 no HTML: o CSS inverte (row-reverse) e pinta a estrela
                            // escolhida e as seguintes no HTML — que na tela são as anteriores.
                            $minhaNota = (int) ($my_review['rating'] ?? 0);
                            for ($n = 5; $n >= 1; $n--): ?>
                                <label class="product-review-star">
                                    <input type="radio" name="rating" value="<?= $n ?>"<?= $minhaNota === $n ? ' checked' : '' ?>>
                                    <span class="visually-hidden"><?= $n ?> estrela<?= $n > 1 ? 's' : '' ?></span>
                                    <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96L12 2.5z"/></svg>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <label class="ui-field"><span>Comentário (opcional)</span>
                            <textarea name="comment" rows="3" maxlength="1000" placeholder="Conte como foi: tamanho, tecido, entrega…"><?= htmlspecialchars((string) ($my_review['comment'] ?? '')) ?></textarea>
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm"><?= !empty($my_review) ? 'Atualizar avaliação' : 'Enviar avaliação' ?></button>
                    </form>
                <?php elseif (empty($is_logged)): ?>
                    <p class="product-reviews-hint">Comprou este produto? <a href="<?= base_url('?auth=login') ?>">Entre na sua conta</a> para avaliar.</p>
                <?php else: ?>
                    <p class="product-reviews-hint">Só quem comprou este produto pode avaliá-lo.</p>
                <?php endif; ?>
            </div>
            <div class="product-reviews-list">
                <?php foreach ($reviews as $rv): ?>
                    <article class="product-review">
                        <header>
                            <?= ui_stars((float) $rv['rating'], 14) ?>
                            <strong><?= htmlspecialchars($rv['autor']) ?></strong>
                            <time datetime="<?= htmlspecialchars($rv['created_at']) ?>"><?= htmlspecialchars(date('d/m/Y', strtotime($rv['created_at']))) ?></time>
                        </header>
                        <?php if ($rv['comment'] !== null): ?>
                            <p><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($related_products)): ?>
        <section class="product-related" aria-labelledby="product-related-title">
            <h2 id="product-related-title" class="product-section-title">Você também pode gostar</h2>
            <div class="products-grid products-grid--v2 product-related-grid">
                <?php foreach ($related_products as $p): ?>
                    <?php require __DIR__ . '/_product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/store-product.js') . '"></script>';
require __DIR__ . '/layout_store.php';
