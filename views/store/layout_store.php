<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? $store['name']) ?></title>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="store-front">
    <header class="store-header">
        <div class="container">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-name"><?= htmlspecialchars($store['name']) ?></a>
            <nav class="store-header-links">
                <?php if (!empty($can_see_panel)): ?>
                    <a href="<?= base_url("painel/{$store['slug']}") ?>" class="panel-link">Painel da Loja</a>
                <?php endif; ?>
                <a href="<?= base_url("loja/{$store['slug']}/carrinho") ?>" class="cart-link">Carrinho</a>
                <div class="store-user-menu-wrap">
                    <button type="button" class="store-user-btn" aria-label="Minha conta" title="Minha conta">
                        <span class="store-user-icon" aria-hidden="true">👤</span>
                    </button>
                    <div id="store-user-popup" class="store-user-popup hidden">
                        <div class="store-user-popup-header">Minha conta</div>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>" class="store-user-popup-link">
                            <span class="store-user-popup-icon" aria-hidden="true">📦</span>
                            <span>Meus pedidos</span>
                        </a>
                        <a href="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>" class="store-user-popup-link">
                            <span class="store-user-popup-icon" aria-hidden="true">📍</span>
                            <span>Meus endereços</span>
                        </a>
                    </div>
                </div>
                <a href="<?= base_url('lojas') ?>" class="store-back-link">Voltar para Lojas</a>
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
          popup.classList.toggle('hidden');
        });
        document.addEventListener('click', function() { popup.classList.add('hidden'); });
        popup.addEventListener('click', function(e) { e.stopPropagation(); });
      }
    })();
    </script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
