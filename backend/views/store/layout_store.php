<?php
// A vitrine segue a paleta da LOJA, não a preferência claro/escuro de quem
// visita: fundo escuro usa o tema escuro, fundo claro (ou sem cor) o claro.
// Antes as duas coisas se misturavam — cards escuros sobre fundo creme, ícone
// branco sobre cartão branco no modo claro — e não havia combinação que
// funcionasse para todas as lojas.
$__storeBg = (isset($store['background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $store['background_color'])) ? (string) $store['background_color'] : null;
$__storeTheme = ($__storeBg !== null && hex_color_is_dark($__storeBg)) ? 'dark' : 'light';

// Descrição e imagem da página para buscadores e para a prévia do link no
// WhatsApp e no Instagram. Cada view pode definir as suas antes do require.
$__storeSlogan = trim((string) ($store['slogan'] ?? ''));
$__storeCity = trim((string) ($store['city'] ?? ''));
if (empty($meta_description)) {
    $meta_description = $__storeSlogan !== ''
        ? $store['name'] . ': ' . $__storeSlogan . ($__storeCity !== '' ? ' Compre online com entrega ou retirada em ' . $__storeCity . '.' : ' Compre online.')
        : 'Compre online na ' . $store['name'] . ($__storeCity !== '' ? ', em ' . $__storeCity : '') . '. Pague com PIX ou cartão.';
}
$meta_description = mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $meta_description) ?? ''), 0, 160, '…');
if (empty($meta_image)) {
    $__banner = trim((string) ($store['banner_path'] ?? ''));
    $meta_image = $__banner !== '' ? base_url('uploads/' . str_replace('\\', '/', $__banner)) : null;
}
$__pageUrl =(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
$__pageTitle = (string) ($title ?? $store['name']);
if ($__pageTitle !== $store['name'] && strpos($__pageTitle, $store['name']) === false) {
    $__pageTitle .= ' | ' . $store['name'];
}
$__logged = logged_in();
$__userName = '';
if ($__logged) {
    $__me = (new \App\Repositories\UserRepository())->find((int) $_SESSION['logged_user_id']);
    $__userName = trim((string) strtok((string) ($__me['name'] ?? ''), ' '));
}
$__storeUrl = base_url("loja/{$store['slug']}");
$__entrarUrl = base_url("loja/{$store['slug']}/entrar");
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $__storeTheme ?>" data-theme-locked="1">
<head>
    <meta charset="UTF-8">
    <?= csrf_meta() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($__pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <?php if (!empty($meta_robots)): ?><meta name="robots" content="<?= htmlspecialchars($meta_robots) ?>"><?php endif; ?>
    <meta property="og:type" content="<?= !empty($meta_og_type) ? htmlspecialchars($meta_og_type) : 'website' ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($store['name']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($__pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($__pageUrl) ?>">
    <meta property="og:locale" content="pt_BR">
    <?php if (!empty($meta_image)): ?><meta property="og:image" content="<?= htmlspecialchars(preg_match('#^https?://#', (string) $meta_image) ? (string) $meta_image : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . (string) $meta_image)) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($meta_image) ? 'summary_large_image' : 'summary' ?>">
    <?= favicon_link_tag($store ?? []) ?>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/ui.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/store-pages.css') ?>">
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
$__wa = store_whatsapp_url($store ?? [], 'Olá! Vim pela loja ' . ($store['name'] ?? '') . ' e tenho uma dúvida.');
$__showFab = $__wa !== null && empty($hide_whatsapp_fab);
$__cartCount = store_cart_count((int) $store['id']);
?>
<body class="store-front<?= $storeBackgroundColor ? ' store-custom-bg' : '' ?><?= $storeSurfaceClass ?><?= !empty($store_header_search) ? ' store-has-search' : '' ?><?= $__showFab ? ' has-whatsapp-fab' : '' ?>"<?= $bodyStyle ? ' style="' . $bodyStyle . '"' : '' ?> data-store-id="<?= (int) $store['id'] ?>" data-store-slug-ui="<?= htmlspecialchars($store['slug']) ?>" data-logged="<?= $__logged ? '1' : '0' ?>">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>
    <header class="store-header">
        <div class="container store-header-inner">
            <a href="<?= $__storeUrl ?>" class="store-brand">
                <img src="<?= htmlspecialchars(store_brand_icon_url($store ?? [])) ?>" alt="" class="store-brand-icon" width="36" height="36" decoding="async">
                <?php if ($__storeSlogan !== ''): ?>
                    <span class="store-brand-copy">
                        <span class="store-brand-text"><?= htmlspecialchars($store['name']) ?></span>
                        <span class="store-brand-slogan"><?= htmlspecialchars($__storeSlogan) ?></span>
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
                <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="store-nav-link cart-link" aria-label="Carrinho<?= $__cartCount > 0 ? ', ' . $__cartCount . ' ' . ($__cartCount === 1 ? 'item' : 'itens') : '' ?>">
                    <span class="cart-link-icon"><?= ui_icon('cart', 18) ?><span class="store-cart-count<?= $__cartCount > 0 ? '' : ' hidden' ?>" data-cart-count aria-hidden="true"><?= $__cartCount > 99 ? '99+' : $__cartCount ?></span></span>
                    <span>Carrinho</span>
                </a>
                <span class="store-header-divider" aria-hidden="true"></span>
                <div class="store-user-menu-wrap">
                    <button type="button" class="store-user-btn" aria-label="<?= $__logged ? 'Minha conta' : 'Entrar ou criar conta' ?>" aria-expanded="false" aria-haspopup="true" aria-controls="store-user-popup" title="Minha conta">
                        <svg class="store-user-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span class="store-user-label"><?= $__logged ? 'Minha conta' : 'Entrar' ?></span>
                    </button>
                    <div id="store-user-popup" class="store-user-popup hidden">
                        <?php if ($__logged): ?>
                            <div class="store-user-popup-header">Olá<?= $__userName !== '' ? ', ' . htmlspecialchars($__userName) : '' ?></div>
                        <?php else: ?>
                            <div class="store-user-popup-guest">
                                <p class="store-user-popup-guest-text">Entre para finalizar compras, acompanhar pedidos e salvar favoritos.</p>
                                <a href="<?= $__entrarUrl ?>" class="btn btn-primary btn-sm store-user-popup-cta"><?= ui_icon('log-in', 16) ?><span>Entrar</span></a>
                                <a href="<?= $__entrarUrl ?>?modo=cadastro" class="store-user-popup-secondary">Criar conta</a>
                            </div>
                        <?php endif; ?>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="store-user-popup-link">
                            <?= ui_icon('package', 18, 'store-user-popup-svg') ?>
                            <span>Meus pedidos</span>
                        </a>
                        <a href="<?= base_url("loja/{$store['slug']}/favoritos") ?>" class="store-user-popup-link">
                            <?= ui_icon('heart', 18, 'store-user-popup-svg') ?>
                            <span>Meus favoritos</span>
                        </a>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>" class="store-user-popup-link">
                            <?= ui_icon('pin', 18, 'store-user-popup-svg') ?>
                            <span>Meus endereços</span>
                        </a>
                        <?php if ($__logged): ?>
                            <a href="<?= base_url('sair') ?>?volta=<?= rawurlencode('loja/' . $store['slug']) ?>" class="store-user-popup-link store-user-popup-link--logout">
                                <?= ui_icon('log-out', 18, 'store-user-popup-svg') ?>
                                <span>Sair</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($__logged): ?>
                <a href="<?= base_url('lojas') ?>" class="store-nav-link store-back-link"><?= ui_icon('store', 18) ?><span>Outras lojas</span></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="store-main" id="conteudo" tabindex="-1">
        <?php // Avisos de redirecionamento (ex.: "sem permissão" vindo do painel). As telas que mostram o próprio aviso já o consumiram. ?>
        <?php if (!empty($_SESSION['_error'])): ?>
        <div class="alert alert-error store-flash" role="alert"><?= htmlspecialchars((string) $_SESSION['_error']) ?></div>
        <?php unset($_SESSION['_error']); endif; ?>
        <?= $content ?? '' ?>
    </main>
    <?php require __DIR__ . '/_store_footer.php'; ?>
    <?php if ($__showFab): ?>
        <a class="store-whatsapp-fab" href="<?= htmlspecialchars($__wa) ?>" target="_blank" rel="noopener" aria-label="Falar com a loja no WhatsApp" title="Falar com a loja no WhatsApp">
            <img src="<?= asset('images/whatsapp.png') ?>" alt="" width="56" height="56" decoding="async">
        </a>
    <?php endif; ?>
    <?php // Gradiente da meia estrela (ui_stars), definido uma vez para a página toda. ?>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs><linearGradient id="ui-star-half"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs></svg>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/store-ui.js') ?>"></script>
    <script src="<?= asset('js/store-extras.js') ?>"></script>
    <script src="<?= asset('js/form-helpers.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
