<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? 'Painel') ?> — <?= htmlspecialchars($store['name']) ?></title>
    <link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="panel<?= !empty($panel_readonly) ? ' panel-readonly' : '' ?>">
<?php
$__panelSlug = (string) ($store['slug'] ?? '');
$__reqPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$__panelNav = '';
if ($__panelSlug !== '' && preg_match('~/painel/' . preg_quote($__panelSlug, '~') . '(?:/([^/?#]+))?~', $__reqPath, $__m)) {
    $__panelNav = (isset($__m[1]) && $__m[1] !== '') ? strtolower($__m[1]) : 'dashboard';
}
$__navActive = static function (string $key) use ($__panelNav): string {
    return strtolower($key) === $__panelNav ? 'panel-nav-link active' : 'panel-nav-link';
};
?>
    <aside class="panel-sidebar">
        <h2 class="panel-sidebar-title"><?= htmlspecialchars($store['name']) ?></h2>
        <nav class="panel-sidebar-nav" aria-label="Menu do painel">
            <a class="<?= $__navActive('dashboard') ?>" href="<?= base_url("painel/{$store['slug']}") ?>">Dashboard</a>
            <a class="<?= $__navActive('produtos') ?>" href="<?= base_url("painel/{$store['slug']}/produtos") ?>">Produtos</a>
            <a class="<?= $__navActive('estoque') ?>" href="<?= base_url("painel/{$store['slug']}/estoque") ?>">Estoque</a>
            <a class="<?= $__navActive('entregas') ?>" href="<?= base_url("painel/{$store['slug']}/entregas") ?>">Entregas</a>
            <a class="<?= $__navActive('pdv') ?>" href="<?= base_url("painel/{$store['slug']}/pdv") ?>">PDV</a>
            <?php if (empty($panel_readonly)): ?>
            <a class="<?= $__navActive('funcionarios') ?>" href="<?= base_url("painel/{$store['slug']}/funcionarios") ?>">Funcionários</a>
            <a class="<?= $__navActive('clientes') ?>" href="<?= base_url("painel/{$store['slug']}/clientes") ?>">Clientes</a>
            <?php endif; ?>
            <a class="<?= $__navActive('hierarquia') ?>" href="<?= base_url("painel/{$store['slug']}/hierarquia") ?>">Hierarquia</a>
            <a class="<?= $__navActive('relatorios') ?>" href="<?= base_url("painel/{$store['slug']}/relatorios") ?>">Relatórios</a>
            <?php if (empty($panel_readonly)): ?>
            <a class="<?= $__navActive('configuracoes') ?>" href="<?= base_url("painel/{$store['slug']}/configuracoes") ?>">Configurações</a>
            <?php endif; ?>
        </nav>
        <p class="panel-sidebar-footer"><a class="panel-store-link" href="<?= base_url("loja/{$store['slug']}") ?>" target="_blank" rel="noopener">Ver loja →</a></p>
    </aside>
    <main class="panel-main" data-store-slug="<?= htmlspecialchars($store['slug'] ?? '') ?>">
        <?= $content ?? '' ?>
    </main>
    <script>window.panelReadonly = <?= !empty($panel_readonly) ? 'true' : 'false' ?>;</script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
