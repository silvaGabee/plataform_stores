<?php if (!empty($vitrine_categories)): ?>
    <nav class="store-vitrine-categories-wrap" aria-label="Categorias da loja">
        <div class="store-vitrine-categories-inner">
            <h2 class="store-vitrine-categories-title">Categorias</h2>
            <div class="h-scroll-mask h-scroll-mask--store-vitrine">
                <div class="store-vitrine-categories-scroll h-scroll-mask__track">
                <ul class="store-vitrine-categories">
                    <?php
                    $activeId = isset($active_category_id) ? (int) $active_category_id : 0;
                    foreach ($vitrine_categories as $cat):
                        $isActive = $activeId > 0 && $activeId === (int) $cat['id'];
                        $catUrl = base_url('loja/' . $store['slug'] . '/categoria/' . $cat['id']);
                        ?>
                        <li class="store-vitrine-category-item">
                            <a href="<?= htmlspecialchars($catUrl) ?>" class="store-vitrine-category-link js-store-category-link<?= $isActive ? ' store-vitrine-category-link--active' : '' ?>" data-category-id="<?= (int) $cat['id'] ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                <span class="store-vitrine-category-icon store-vitrine-category-icon--storefront">
                                    <img src="<?= htmlspecialchars($cat['icon_url']) ?>" alt="" width="40" height="40" decoding="async" loading="lazy" referrerpolicy="no-referrer">
                                </span>
                                <span class="store-vitrine-category-name"><?= htmlspecialchars($cat['name']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>
