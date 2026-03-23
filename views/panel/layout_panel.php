<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? 'Painel') ?> — <?= htmlspecialchars($store['name']) ?></title>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="panel<?= !empty($panel_readonly) ? ' panel-readonly' : '' ?>">
    <aside class="panel-sidebar">
        <h2><?= htmlspecialchars($store['name']) ?></h2>
        <nav>
            <a href="<?= base_url("painel/{$store['slug']}") ?>">Dashboard</a>
            <a href="<?= base_url("painel/{$store['slug']}/produtos") ?>">Produtos</a>
            <a href="<?= base_url("painel/{$store['slug']}/estoque") ?>">Estoque</a>
            <a href="<?= base_url("painel/{$store['slug']}/entregas") ?>">Entregas</a>
            <a href="<?= base_url("painel/{$store['slug']}/pdv") ?>">PDV</a>
            <?php if (empty($panel_readonly)): ?>
            <a href="<?= base_url("painel/{$store['slug']}/funcionarios") ?>">Funcionários</a>
            <a href="<?= base_url("painel/{$store['slug']}/clientes") ?>">Clientes</a>
            <?php endif; ?>
            <a href="<?= base_url("painel/{$store['slug']}/hierarquia") ?>">Hierarquia</a>
            <a href="<?= base_url("painel/{$store['slug']}/relatorios") ?>">Relatórios</a>
        </nav>
        <p><a href="<?= base_url("loja/{$store['slug']}") ?>" target="_blank">Ver loja →</a></p>
    </aside>
    <main class="panel-main" data-store-slug="<?= htmlspecialchars($store['slug'] ?? '') ?>">
        <?= $content ?? '' ?>
    </main>
    <script>window.panelReadonly = <?= !empty($panel_readonly) ? 'true' : 'false' ?>;</script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
