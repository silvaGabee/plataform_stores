<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Plataforma de Lojas') ?></title>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
    <header class="app-header">
        <div class="container">
            <a href="<?= base_url() ?>" class="app-header-brand">Plataforma de Lojas</a>
        </div>
    </header>
    <?= $content ?? '' ?>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
