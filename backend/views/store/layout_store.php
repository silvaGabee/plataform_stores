<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? $store['name']) ?></title>
    <link rel="icon" href="<?= favicon_url() ?>" sizes="any">
    <link rel="shortcut icon" href="<?= favicon_url() ?>" type="image/x-icon">
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="store-front">
    <header class="store-header">
        <div class="container store-header-inner">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-brand">
                <img src="<?= favicon_url() ?>" alt="" class="store-brand-icon" width="36" height="36" decoding="async">
                <span class="store-brand-text"><?= htmlspecialchars($store['name']) ?></span>
            </a>
            <nav class="store-header-links" aria-label="Menu da loja">
                <?php if (!empty($can_see_panel)): ?>
                    <a href="<?= base_url("painel/{$store['slug']}") ?>" class="store-nav-link panel-link">Painel da Loja</a>
                <?php endif; ?>
                <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="store-nav-link cart-link">Carrinho</a>
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
                        <a href="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>" class="store-user-popup-link" role="menuitem">
                            <svg class="store-user-popup-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>Meus endereços</span>
                        </a>
                    </div>
                </div>
                <a href="<?= base_url('lojas') ?>" class="store-nav-link store-back-link">Voltar para Lojas</a>
            </nav>
        </div>
    </header>
    <main class="store-main">
        <?= $content ?? '' ?>
    </main>
    <footer class="store-footer">
        <div class="container"><?= htmlspecialchars($store['name']) ?> &copy; <?= date('Y') ?></div>
    </footer>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script>
    (function(){
      var btn = document.querySelector('.store-user-btn');
      var popup = document.getElementById('store-user-popup');
      if (btn && popup) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          var open = popup.classList.toggle('hidden') === false;
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
