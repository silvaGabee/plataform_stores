<?php
// A vitrine segue a paleta da LOJA, não a preferência claro/escuro de quem
// visita: fundo escuro usa o tema escuro, fundo claro (ou sem cor) o claro.
// Antes as duas coisas se misturavam — cards escuros sobre fundo creme, ícone
// branco sobre cartão branco no modo claro — e não havia combinação que
// funcionasse para todas as lojas.
$__storeBg = (isset($store['background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $store['background_color'])) ? (string) $store['background_color'] : null;
$__storeTheme = ($__storeBg !== null && hex_color_is_dark($__storeBg)) ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $__storeTheme ?>" data-theme-locked="1">
<head>
    <meta charset="UTF-8">
    <?= csrf_meta() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? $store['name']) ?></title>
    <?= favicon_link_tag($store ?? []) ?>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/ui.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
    <script src="<?= asset('js/csrf.js') ?>"></script>
    <script src="<?= asset('js/ui-kit.js') ?>"></script>
</head>
<?php
$storeBackgroundColor = (isset($store['background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $store['background_color'])) ? $store['background_color'] : null;
$storeCategoriesBg = null;
$storeBannerBg = null;
try {
    $cfgRepo = new \App\Repositories\StoreDashboardConfigRepository();
    $cfg = $cfgRepo->getConfig((int) ($store['id'] ?? 0));
    if (!empty($cfg['appearance']) && is_array($cfg['appearance'])) {
        $storeCategoriesBg = isset($cfg['appearance']['categories_background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $cfg['appearance']['categories_background_color']) ? $cfg['appearance']['categories_background_color'] : null;
        $storeBannerBg = isset($cfg['appearance']['banner_background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $cfg['appearance']['banner_background_color']) ? $cfg['appearance']['banner_background_color'] : null;
    }
} catch (\Throwable $e) {
    // ignore
}
$bodyStyle = '';
if ($storeBackgroundColor) {
    $bodyStyle .= 'background-color: ' . htmlspecialchars($storeBackgroundColor, ENT_QUOTES, 'UTF-8') . ';';
}
if ($storeCategoriesBg) {
    $bodyStyle .= " --store-categories-bg: " . htmlspecialchars($storeCategoriesBg, ENT_QUOTES, 'UTF-8') . ";";
}
if ($storeBannerBg) {
    $bodyStyle .= " --store-banner-bg: " . htmlspecialchars($storeBannerBg, ENT_QUOTES, 'UTF-8') . ";";
}
?>
<?php
// O texto que fica direto sobre a cor da loja (cabeçalho, categorias,
// títulos, rodapé) segue a cor do FUNDO, não o tema claro/escuro de quem
// visita: com fundo azul-marinho e tema claro, nome da loja e categorias
// eram pintados quase de preto e sumiam.
$storeSurfaceClass = '';
if ($storeBackgroundColor) {
    $storeSurfaceClass .= hex_color_is_dark($storeBackgroundColor) ? ' store-bg-dark' : ' store-bg-light';
    $catsSurface = $storeCategoriesBg ?: $storeBackgroundColor;
    $storeSurfaceClass .= hex_color_is_dark($catsSurface) ? ' store-cats-dark' : ' store-cats-light';
    $bodyStyle .= ' --store-bg: ' . htmlspecialchars($storeBackgroundColor, ENT_QUOTES, 'UTF-8') . ';';
}
?>
<body class="store-front<?= $storeBackgroundColor ? ' store-custom-bg' : '' ?><?= $storeSurfaceClass ?><?= !empty($store_header_search) ? ' store-has-search' : '' ?>"<?= $bodyStyle ? ' style="' . $bodyStyle . '"' : '' ?>>
    <?php $storeSlogan = trim((string) ($store['slogan'] ?? '')); ?>
    <header class="store-header">
        <div class="container store-header-inner">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-brand">
                <img src="<?= htmlspecialchars(store_brand_icon_url($store ?? [])) ?>" alt="" class="store-brand-icon" width="36" height="36" decoding="async">
                <?php if ($storeSlogan !== ''): ?>
                    <span class="store-brand-copy">
                        <span class="store-brand-text"><?= htmlspecialchars($store['name']) ?></span>
                        <span class="store-brand-slogan"><?= htmlspecialchars($storeSlogan) ?></span>
                    </span>
                <?php else: ?>
                    <span class="store-brand-text"><?= htmlspecialchars($store['name']) ?></span>
                <?php endif; ?>
            </a>
            <?php if (!empty($store_header_search)): ?>
                <?php require __DIR__ . '/_vitrine_search.php'; ?>
            <?php endif; ?>
            <nav class="store-header-links" aria-label="Menu da loja">
                <?php if (!empty($can_see_panel)): ?>
                    <a href="<?= base_url("painel/{$store['slug']}") ?>" class="store-nav-link panel-link">Painel da Loja</a>
                <?php endif; ?>
                <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="store-nav-link cart-link"><?= ui_icon('cart', 18) ?><span>Carrinho</span></a>
                <span class="store-header-divider" aria-hidden="true"></span>
                <div class="store-user-menu-wrap">
                    <button type="button" class="store-user-btn" aria-label="Minha conta" aria-expanded="false" aria-haspopup="true" title="Minha conta">
                        <svg class="store-user-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span class="store-user-label">Minha conta</span>
                    </button>
                    <div id="store-user-popup" class="store-user-popup hidden" role="menu">
                        <div class="store-user-popup-header">Minha conta</div>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="store-user-popup-link" role="menuitem">
                            <svg class="store-user-popup-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            <span>Meus pedidos</span>
                        </a>
                        <a href="<?= base_url("loja/{$store['slug']}/favoritos") ?>" class="store-user-popup-link" role="menuitem">
                            <svg class="store-user-popup-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C1 8.6 2.9 5 6.4 5c2.1 0 3.6 1.2 4.6 2.6C12 6.2 13.5 5 15.6 5 19.1 5 21 8.6 19.6 11.8 17.5 16.4 12 21 12 21z"/></svg>
                            <span>Meus favoritos</span>
                        </a>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>" class="store-user-popup-link" role="menuitem">
                            <svg class="store-user-popup-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>Meus endereços</span>
                        </a>
                    </div>
                </div>
                <?php if (logged_in()): ?>
                <a href="<?= base_url('lojas') ?>" class="store-nav-link store-back-link"><?= ui_icon('store', 18) ?><span>Outras lojas</span></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="store-main">
        <?= $content ?? '' ?>
    </main>
    <footer class="store-footer">
        <div class="container"><?= htmlspecialchars($store['name']) ?> &copy; <?= date('Y') ?></div>
    </footer>
    <?php $__wa = store_whatsapp_url($store ?? [], 'Olá! Vim pela loja ' . ($store['name'] ?? '') . ' e tenho uma dúvida.'); ?>
    <?php if ($__wa !== null): ?>
        <a class="store-whatsapp-fab" href="<?= htmlspecialchars($__wa) ?>" target="_blank" rel="noopener" aria-label="Falar com a loja no WhatsApp" title="Falar com a loja no WhatsApp">
            <img src="<?= asset('images/whatsapp.png') ?>" alt="" width="56" height="56" decoding="async">
        </a>
    <?php endif; ?>
    <?php // Gradiente da meia estrela (ui_stars), definido uma vez para a página toda. ?>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs><linearGradient id="ui-star-half"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs></svg>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/store-extras.js') ?>"></script>
    <script>
    (function(){
      var btn = document.querySelector('.store-user-btn');
      var popup = document.getElementById('store-user-popup');
      if (btn && popup) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          var open = popup.classList.toggle('hidden') === false;
          // No celular o menu é fixo na tela (vitrine.css): abre logo abaixo do botão.
          if (open) popup.style.setProperty('--store-user-popup-top', btn.getBoundingClientRect().bottom + 'px');
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function() {
          popup.classList.add('hidden');
          btn.setAttribute('aria-expanded', 'false');
        });
        popup.addEventListener('click', function(e) { e.stopPropagation(); });
      }
    })();
    </script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
