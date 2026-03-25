<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Plataforma de Lojas') ?></title>
    <link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
    <header class="app-header">
        <div class="container">
            <a href="<?= base_url() ?>" class="app-header-brand">Plataforma de Lojas</a>
            <?php if (logged_in()): ?>
                <nav class="app-header-actions" aria-label="Conta">
                    <a href="<?= base_url('minha-conta') ?>" class="app-header-minha-conta">
                        <svg class="app-header-minha-conta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Minha conta</span>
                    </a>
                    <a href="<?= base_url('sair') ?>" class="link-logout">Sair</a>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <?= $content ?? '' ?>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
