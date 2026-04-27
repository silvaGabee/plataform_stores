<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= rtrim(base_url(), '/') ?>">
    <title><?= htmlspecialchars($title ?? 'Painel') ?> — <?= htmlspecialchars($store['name']) ?></title>
    <?php $__panel_icon = htmlspecialchars(store_brand_icon_url($store ?? []), ENT_QUOTES, 'UTF-8'); ?>
    <link rel="icon" href="<?= $__panel_icon ?>" sizes="any">
    <link rel="shortcut icon" href="<?= $__panel_icon ?>" type="image/x-icon">
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
$__ic = static function (string $d): string {
    return '<svg class="panel-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '"/></svg>';
};
?>
    <aside class="panel-sidebar">
        <div class="panel-sidebar-brand">
            <h2 class="panel-sidebar-title"><?= htmlspecialchars($store['name']) ?></h2>
            <p class="panel-sidebar-tagline">Painel da loja</p>
        </div>
        <nav class="panel-sidebar-nav" aria-label="Menu do painel">
            <a class="<?= $__navActive('dashboard') ?>" href="<?= base_url("painel/{$store['slug']}") ?>"><?= $__ic('M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10') ?><span class="panel-nav-text">Dashboard</span></a>
            <a class="<?= $__navActive('produtos') ?>" href="<?= base_url("painel/{$store['slug']}/produtos") ?>"><?= $__ic('M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z M3.27 6.96L12 12.01l8.73-5.05 M12 22.08V12') ?><span class="panel-nav-text">Produtos</span></a>
            <a class="<?= $__navActive('estoque') ?>" href="<?= base_url("painel/{$store['slug']}/estoque") ?>"><?= $__ic('M12 2L2 7l10 5 10-5-10-5z M2 17l10 5 10-5 M2 12l10 5 10-5') ?><span class="panel-nav-text">Estoque</span></a>
            <a class="<?= $__navActive('entregas') ?>" href="<?= base_url("painel/{$store['slug']}/entregas") ?>"><?= $__ic('M1 3h15v13H1z M16 8h4l3 3v5h-7V8z M5 18a2 2 0 1 0 4 0 2 2 0 0 0-4 0z M15 18a2 2 0 1 0 4 0 2 2 0 0 0-4 0z') ?><span class="panel-nav-text">Entregas</span></a>
            <a class="<?= $__navActive('pdv') ?>" href="<?= base_url("painel/{$store['slug']}/pdv") ?>"><?= $__ic('M4 4h16v4H4z M4 10h16v10H4z M8 14h4') ?><span class="panel-nav-text">PDV</span></a>
            <?php if (empty($panel_readonly)): ?>
            <a class="<?= $__navActive('funcionarios') ?>" href="<?= base_url("painel/{$store['slug']}/funcionarios") ?>"><?= $__ic('M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75') ?><span class="panel-nav-text">Funcionários</span></a>
            <?php endif; ?>
            <a class="<?= $__navActive('hierarquia') ?>" href="<?= base_url("painel/{$store['slug']}/hierarquia") ?>"><?= $__ic('M4 4h6v5H4zM14 4h6v5h-6zM9 14h6v6H9z') ?><span class="panel-nav-text">Hierarquia</span></a>
            <?php if (empty($panel_readonly)): ?>
            <a class="<?= $__navActive('analyzing-bi') ?>" href="<?= base_url("painel/{$store['slug']}/analyzing-bi") ?>"><?= $__ic('M4 19h16M4 15h10M4 11h16M4 7h12M9 3v4') ?><span class="panel-nav-text">Analyzing BI</span></a>
            <a class="<?= $__navActive('configuracoes') ?>" href="<?= base_url("painel/{$store['slug']}/configuracoes") ?>"><svg class="panel-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg><span class="panel-nav-text">Configurações</span></a>
            <?php endif; ?>
        </nav>
        <p class="panel-sidebar-footer"><a class="panel-store-link" href="<?= base_url("loja/{$store['slug']}") ?>" target="_blank" rel="noopener"><span class="panel-store-link-icon" aria-hidden="true">↗</span> Ver vitrine</a></p>
    </aside>
    <main class="panel-main<?= !empty($panel_main_extra_class) ? ' ' . htmlspecialchars((string) $panel_main_extra_class, ENT_QUOTES, 'UTF-8') : '' ?>" data-store-slug="<?= htmlspecialchars($store['slug'] ?? '') ?>">
        <?= $content ?? '' ?>
    </main>
    <button type="button" id="panel-ai-fab" class="panel-ai-fab" aria-haspopup="dialog" aria-controls="panel-ai-dialog" aria-label="Assistente da loja" title="Assistente da loja">
        <span class="panel-ai-fab-inner" aria-hidden="true">
            <svg class="panel-ai-fab-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M12 3L13.5 8L18 9L13.5 10L12 15L10.5 10L6 9L10.5 8L12 3Z" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round"/>
                <path d="M19 14L19.75 16.25L22 17L19.75 17.75L19 20L18.25 17.75L16 17L18.25 16.25L19 14Z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round" opacity="0.9"/>
                <path d="M5 13L5.6 14.8L7.4 15.4L5.6 16L5 17.8L4.4 16L2.6 15.4L4.4 14.8L5 13Z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round" opacity="0.85"/>
            </svg>
        </span>
    </button>
    <div id="panel-ai-modal" class="panel-ai-modal" hidden>
        <div class="panel-ai-backdrop" tabindex="-1" aria-hidden="true"></div>
        <div id="panel-ai-dialog" class="panel-ai-dialog" role="dialog" aria-modal="true" aria-labelledby="panel-ai-title">
            <div class="panel-ai-header">
                <div class="panel-ai-header-brand">
                    <span class="panel-ai-header-mark" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="panel-ai-header-text">
                        <div class="panel-ai-title-row">
                            <h2 id="panel-ai-title" class="panel-ai-title">Assistente da loja</h2>
                            <span class="panel-ai-status" title="Serviço ativo">
                                <span class="panel-ai-status-dot" aria-hidden="true"></span>
                                Disponível
                            </span>
                        </div>
                        <p class="panel-ai-subtitle">Copiloto para dados da sua loja · respostas em linguagem natural</p>
                    </div>
                </div>
                <button type="button" class="panel-ai-close" id="panel-ai-close" aria-label="Fechar">
                    <svg class="panel-ai-close-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="panel-ai-scroll">
                <div class="panel-ai-disclaimer" role="note">
                    <span class="panel-ai-disclaimer-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <path d="M12 16V12M12 8H12.01M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="panel-ai-disclaimer-body">
                        <span class="panel-ai-disclaimer-label">Aviso</span>
                        <p class="panel-ai-disclaimer-text">Lembre-se: isso é uma IA. Algumas informações podem estar erradas — confirme dados sensíveis antes de agir.</p>
                    </div>
                </div>
                <div id="panel-ai-messages" class="panel-ai-messages" aria-live="polite">
                    <div class="panel-ai-empty" id="panel-ai-empty">
                        <div class="panel-ai-empty-visual" aria-hidden="true">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3L13.5 8L18 9L13.5 10L12 15L10.5 10L6 9L10.5 8L12 3Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" opacity="0.5"/>
                                <path d="M19 14L19.75 16.25L22 17L19.75 17.75L19 20L18.25 17.75L16 17L18.25 16.25L19 14Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round" opacity="0.4"/>
                            </svg>
                        </div>
                        <p class="panel-ai-empty-title">Em que posso ajudar?</p>
                        <p class="panel-ai-empty-desc">Pode perguntar por vendas, produtos, entregas ou pedir um resumo — as respostas usam os dados do painel quando aplicável.</p>
                    </div>
                </div>
            </div>
            <form id="panel-ai-form" class="panel-ai-form">
                <label class="panel-ai-compose-label" for="panel-ai-input">A sua pergunta</label>
                <div class="panel-ai-compose">
                    <div class="panel-ai-input-shell">
                        <textarea id="panel-ai-input" class="panel-ai-input" rows="2" maxlength="2000" placeholder="Ex.: Resumo das vendas da última semana…" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary panel-ai-send" id="panel-ai-send">
                        <span class="panel-ai-send-label">Enviar</span>
                        <span class="panel-ai-send-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>
                </div>
                <p class="panel-ai-form-meta"><span class="panel-ai-form-meta-k">Enter</span> nova linha · <span class="panel-ai-form-meta-k">Ctrl+Enter</span> enviar · máx. 2000 caracteres</p>
            </form>
        </div>
    </div>
    <script>window.panelReadonly = <?= !empty($panel_readonly) ? 'true' : 'false' ?>;</script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/panel-ai-assistant.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
