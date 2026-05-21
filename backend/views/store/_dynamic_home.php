<?php if (!empty($products)): ?>
    <?php
    $catalog_title = 'Catálogo';
    $catalog_desc = count($products) === 1 ? '1 produto disponível' : count($products) . ' produtos disponíveis';
    require __DIR__ . '/_product_grid.php';
    ?>
<?php else: ?>
    <?php
    $empty_title = 'Catálogo em preparação';
    $empty_desc = 'Ainda não há produtos publicados nesta vitrine. Volte em breve ou fale com a loja para mais informações.';
    require __DIR__ . '/_vitrine_empty.php';
    ?>
<?php endif; ?>
