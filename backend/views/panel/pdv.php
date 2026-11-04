<?php
/** @var array $store */
$__pdvIcon = static function (string $d, int $size = 18): string {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};
ob_start();
?>
<div class="panel-content pdv" id="pdv-app" data-state="loading">
    <header class="pdv-topbar">
        <div class="pdv-topbar-id">
            <p class="pdv-eyebrow">Ponto de venda</p>
            <h1 class="pdv-title">Frente de caixa</h1>
            <p class="pdv-topbar-meta">
                <span class="pdv-meta-item"><?= $__pdvIcon('M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 15) ?><span id="pdv-operator"><?= htmlspecialchars($pdv_user_name ?: 'Operador') ?></span></span>
                <span class="pdv-meta-sep" aria-hidden="true">•</span>
                <span class="pdv-meta-item"><?= $__pdvIcon('M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z M12 6v6l4 2', 15) ?><time id="pdv-clock"></time></span>
            </p>
        </div>
        <div class="pdv-register" id="pdv-register" aria-live="polite">
            <span class="pdv-register-pill pdv-register-pill--loading">Verificando caixa…</span>
        </div>
    </header>

    <section class="pdv-kpis" aria-label="Resumo do turno">
        <article class="pdv-kpi">
            <span class="pdv-kpi-label">Vendas no turno</span>
            <strong class="pdv-kpi-value" id="kpi-count">—</strong>
        </article>
        <article class="pdv-kpi">
            <span class="pdv-kpi-label">Faturado no turno</span>
            <strong class="pdv-kpi-value" id="kpi-total">—</strong>
        </article>
        <article class="pdv-kpi">
            <span class="pdv-kpi-label">Ticket médio</span>
            <strong class="pdv-kpi-value" id="kpi-average">—</strong>
        </article>
        <article class="pdv-kpi pdv-kpi--accent">
            <span class="pdv-kpi-label">Dinheiro na gaveta</span>
            <strong class="pdv-kpi-value" id="kpi-cash">—</strong>
        </article>
    </section>

    <div class="pdv-layout">
        <section class="pdv-catalog" aria-label="Catálogo de produtos">
            <div class="pdv-catalog-toolbar">
                <label class="pdv-search" for="pdv-search">
                    <?= $__pdvIcon('M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.35-4.35', 18) ?>
                    <input type="search" id="pdv-search" placeholder="Buscar por nome ou código do produto" autocomplete="off" spellcheck="false">
                    <kbd class="pdv-kbd">F2</kbd>
                </label>
                <label class="pdv-stock-filter">
                    <input type="checkbox" id="pdv-only-stock" checked>
                    <span>Só com estoque</span>
                </label>
            </div>
            <div class="pdv-chips" id="pdv-categories" role="toolbar" aria-label="Filtrar por categoria"></div>
            <div class="pdv-products" id="pdv-product-list" aria-live="polite">
                <?php for ($i = 0; $i < 8; $i++): ?><div class="pdv-product pdv-product--skeleton" aria-hidden="true"></div><?php endfor; ?>
            </div>
        </section>

        <aside class="pdv-ticket" aria-label="Venda atual">
            <div class="pdv-ticket-head">
                <div>
                    <h2 class="pdv-ticket-title">Venda atual</h2>
                    <p class="pdv-ticket-count" id="pdv-cart-count">Nenhum item</p>
                </div>
                <button type="button" class="pdv-link-btn" id="pdv-clear" disabled>
                    <?= $__pdvIcon('M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6', 16) ?> Limpar
                </button>
            </div>

            <div class="pdv-customer">
                <?= $__pdvIcon('M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 16) ?>
                <input type="text" id="pdv-customer-name" maxlength="120" placeholder="Nome do cliente (opcional)" autocomplete="off">
            </div>

            <div class="pdv-lines-wrap">
                <ul class="pdv-lines" id="pdv-cart-items"></ul>
                <div class="pdv-empty" id="pdv-cart-empty">
                    <span class="pdv-empty-icon"><?= $__pdvIcon('M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z M3 6h18 M16 10a4 4 0 0 1-8 0', 28) ?></span>
                    <p class="pdv-empty-title">Venda vazia</p>
                    <p class="pdv-empty-text">Clique em um produto ou digite o código e tecle <kbd class="pdv-kbd">Enter</kbd>.</p>
                </div>
            </div>

            <div class="pdv-totals">
                <div class="pdv-totals-row">
                    <span>Subtotal</span>
                    <span id="pdv-subtotal">R$ 0,00</span>
                </div>
                <div class="pdv-totals-row pdv-totals-row--discount">
                    <button type="button" class="pdv-link-btn" id="pdv-discount-btn" disabled>
                        <?= $__pdvIcon('M19 5L5 19 M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z M17.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', 15) ?>
                        <span id="pdv-discount-label">Adicionar desconto</span>
                        <kbd class="pdv-kbd">F4</kbd>
                    </button>
                    <span id="pdv-discount">— R$ 0,00</span>
                </div>
                <div class="pdv-totals-row pdv-totals-row--total">
                    <span>Total</span>
                    <strong id="pdv-total">R$ 0,00</strong>
                </div>
            </div>

            <button type="button" class="pdv-finish" id="pdv-finish" disabled>
                <span>Finalizar venda</span>
                <kbd class="pdv-kbd pdv-kbd--on-primary">F9</kbd>
            </button>

            <div class="pdv-locked" id="pdv-locked" hidden>
                <span class="pdv-locked-icon"><?= $__pdvIcon('M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4', 26) ?></span>
                <p class="pdv-locked-title">Caixa fechado</p>
                <p class="pdv-locked-text">Abra o caixa com o fundo de troco para começar a vender.</p>
                <button type="button" class="pdv-btn pdv-btn--primary" data-action="open-cash">Abrir caixa</button>
            </div>
        </aside>
    </div>

    <section class="pdv-recent" aria-labelledby="pdv-recent-title">
        <div class="pdv-recent-head">
            <h2 id="pdv-recent-title">Últimas vendas do turno</h2>
            <div class="pdv-shortcuts" aria-label="Atalhos de teclado">
                <span><kbd class="pdv-kbd">F2</kbd> Buscar</span>
                <span><kbd class="pdv-kbd">F4</kbd> Desconto</span>
                <span><kbd class="pdv-kbd">F9</kbd> Finalizar</span>
                <span><kbd class="pdv-kbd">Esc</kbd> Fechar janela</span>
            </div>
        </div>
        <div class="pdv-recent-body" id="pdv-recent">
            <p class="pdv-muted">Nenhuma venda registrada neste turno.</p>
        </div>
    </section>
</div>

<div class="pdv-modal" id="pdv-modal" hidden>
    <div class="pdv-modal-backdrop" data-close></div>
    <div class="pdv-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="pdv-modal-title" tabindex="-1">
        <header class="pdv-modal-head">
            <h2 class="pdv-modal-title" id="pdv-modal-title"></h2>
            <button type="button" class="pdv-modal-close" data-close aria-label="Fechar"><?= $__pdvIcon('M18 6L6 18 M6 6l12 12', 20) ?></button>
        </header>
        <div class="pdv-modal-body" id="pdv-modal-body"></div>
        <footer class="pdv-modal-foot" id="pdv-modal-foot"></footer>
    </div>
</div>

<div class="pdv-toasts" id="pdv-toasts" aria-live="polite" aria-atomic="false"></div>
<div class="pdv-receipt-print" id="pdv-receipt-print" aria-hidden="true"></div>
<?php
$content = ob_get_clean();
$__pdvConfig = [
    'slug' => $store['slug'],
    'storeName' => $store['name'] ?? '',
    'userName' => $pdv_user_name ?? '',
    // Funcionário precisa de aprovação do gerente para dar desconto.
    'isGerente' => !empty($panel_is_gerente),
];
$extra_js = '<script>window.PDV_CONFIG = ' . json_encode($__pdvConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
    . '<script src="' . asset('js/panel-pdv.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
