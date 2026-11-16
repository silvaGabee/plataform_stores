<?php // Título principal da página (o nome da loja só aparecia na imagem do banner). Fica aqui, e não no layout, porque a categoria troca este bloco por AJAX e traz o próprio h1. ?>
<h1 class="visually-hidden"><?= htmlspecialchars($store['name']) ?><?= trim((string) ($store['slogan'] ?? '')) !== '' ? ': ' . htmlspecialchars((string) $store['slogan']) : '' ?></h1>
<?php if (!empty($products)): ?>
    <?php
    $catalog_title = 'Todos os produtos';
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
