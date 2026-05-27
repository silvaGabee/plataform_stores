<?php $content = ob_start(); ?>
<div class="panel-content panel-stock-page">
    <header class="panel-page-header panel-stock-header">
        <div class="panel-stock-header-text">
            <p class="panel-page-eyebrow">Inventário</p>
            <h1>Estoque</h1>
            <p class="panel-lead text-muted">Acompanhe quantidades, identifique itens críticos e registre entradas, saídas ou ajustes.</p>
        </div>
    </header>

    <div class="panel-stock-stats" id="panel-stock-stats" aria-live="polite">
        <div class="panel-stock-stat card">
            <span class="panel-stock-stat-label">Produtos</span>
            <strong class="panel-stock-stat-value" id="stock-stat-total">—</strong>
        </div>
        <div class="panel-stock-stat card panel-stock-stat--warn">
            <span class="panel-stock-stat-label">Baixo (cor / tam.)</span>
            <strong class="panel-stock-stat-value" id="stock-stat-low">—</strong>
        </div>
        <div class="panel-stock-stat card panel-stock-stat--danger">
            <span class="panel-stock-stat-label">Esgotados (cor / tam.)</span>
            <strong class="panel-stock-stat-value" id="stock-stat-zero">—</strong>
        </div>
        <div class="panel-stock-stat card panel-stock-stat--ok">
            <span class="panel-stock-stat-label">Unidades no total</span>
            <strong class="panel-stock-stat-value" id="stock-stat-units">—</strong>
        </div>
    </div>

    <section id="stock-alerts" class="panel-stock-alerts panel-surface-card hidden" aria-labelledby="stock-alerts-title">
        <header class="panel-stock-alerts-head">
            <h2 id="stock-alerts-title" class="panel-stock-alerts-title">Atenção no estoque</h2>
            <p class="panel-stock-alerts-desc text-muted">Cada cor e tamanho usa o mesmo estoque mínimo do produto. Abaixo do mínimo ou zerado aparece aqui.</p>
        </header>
        <ul id="stock-alerts-list" class="panel-stock-alerts-list"></ul>
    </section>

    <div class="panel-stock-toolbar panel-surface-card">
        <label class="panel-stock-search-wrap">
            <span class="visually-hidden">Buscar produto</span>
            <svg class="panel-stock-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
            <input type="search" id="stock-search" class="panel-stock-search" placeholder="Buscar por nome do produto…" autocomplete="off">
        </label>
        <label class="panel-stock-filter-wrap">
            <span class="panel-stock-filter-label">Filtrar</span>
            <select id="stock-filter" class="panel-stock-filter">
                <option value="all">Todos</option>
                <option value="alerts">Com alertas (cor / tam.)</option>
                <option value="low">Estoque baixo</option>
                <option value="zero">Esgotados</option>
                <option value="ok">Em dia</option>
            </select>
        </label>
    </div>

    <div class="panel-stock-table-wrap panel-surface-card">
        <div class="panel-stock-table-head" aria-hidden="true">
            <span class="panel-stock-col panel-stock-col--product">Produto</span>
            <span class="panel-stock-col panel-stock-col--qty">Quantidade</span>
            <span class="panel-stock-col panel-stock-col--min">Mínimo</span>
            <span class="panel-stock-col panel-stock-col--level">Nível</span>
            <span class="panel-stock-col panel-stock-col--status">Status</span>
            <span class="panel-stock-col panel-stock-col--action"></span>
        </div>
        <div id="stock-list" class="panel-stock-list" aria-live="polite"></div>
    </div>

    <div id="adjust-modal" class="modal modal--stock hidden" role="dialog" aria-modal="true" aria-labelledby="adjust-modal-title">
        <div class="modal-content modal-content--stock">
            <header class="stock-modal-head">
                <div class="stock-modal-head-text">
                    <p class="panel-page-eyebrow">Movimentação de estoque</p>
                    <h2 id="adjust-modal-title">Ajustar estoque</h2>
                    <p id="adjust-product-name" class="stock-modal-product-name"></p>
                </div>
                <button type="button" class="stock-modal-close close-modal" aria-label="Fechar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </header>
            <form id="adjust-form" class="stock-modal-form">
                <input type="hidden" id="adjust-product-id">
                <div class="stock-modal-body">
                    <fieldset class="stock-modal-fieldset">
                        <legend class="stock-modal-label">Tipo de movimentação</legend>
                        <div class="stock-type-segments" role="radiogroup" aria-label="Tipo de movimentação">
                            <label class="stock-type-segment stock-type-segment--entrada">
                                <input type="radio" name="adjust-type" value="entrada" checked>
                                <span class="stock-type-segment-icon" aria-hidden="true">+</span>
                                <span class="stock-type-segment-text">Entrada</span>
                            </label>
                            <label class="stock-type-segment stock-type-segment--saida">
                                <input type="radio" name="adjust-type" value="saida">
                                <span class="stock-type-segment-icon" aria-hidden="true">−</span>
                                <span class="stock-type-segment-text">Saída</span>
                            </label>
                            <label class="stock-type-segment stock-type-segment--ajuste">
                                <input type="radio" name="adjust-type" value="ajuste">
                                <span class="stock-type-segment-icon" aria-hidden="true">=</span>
                                <span class="stock-type-segment-text">Ajuste</span>
                            </label>
                        </div>
                        <p id="adjust-type-hint" class="stock-modal-hint">Adiciona unidades ao estoque atual.</p>
                    </fieldset>
                    <div id="adjust-variant-target" class="stock-modal-variant-target hidden">
                        <div class="stock-modal-fields stock-modal-fields--variant-target">
                            <div class="stock-modal-field">
                                <label class="stock-modal-label" for="adjust-variant-color">Cor</label>
                                <select id="adjust-variant-color" class="stock-modal-input stock-modal-select"></select>
                            </div>
                            <div class="stock-modal-field">
                                <label class="stock-modal-label" for="adjust-variant-size" id="adjust-variant-size-label">Tamanho</label>
                                <select id="adjust-variant-size" class="stock-modal-input stock-modal-select"></select>
                            </div>
                        </div>
                        <p id="adjust-variant-preview" class="stock-modal-variant-preview" aria-live="polite"></p>
                        <p class="stock-variant-total">
                            Total do produto: <strong id="adjust-variant-total">0</strong> un.
                        </p>
                    </div>
                    <div class="stock-modal-fields">
                        <div class="stock-modal-field">
                            <label class="stock-modal-label" for="adjust-qty" id="adjust-qty-label">Quantidade</label>
                            <input type="number" id="adjust-qty" class="stock-modal-input stock-modal-input--qty" min="1" required placeholder="0" inputmode="numeric">
                        </div>
                        <div class="stock-modal-field stock-modal-field--wide">
                            <label class="stock-modal-label" for="adjust-reason">Motivo <span class="stock-modal-optional">opcional</span></label>
                            <input type="text" id="adjust-reason" class="stock-modal-input" placeholder="Reposição, inventário, devolução…" maxlength="120">
                        </div>
                    </div>
                </div>
                <footer class="stock-modal-footer">
                    <div class="stock-modal-footer-main">
                        <button type="submit" class="btn btn-primary stock-modal-submit">Confirmar</button>
                        <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                    </div>
                    <?php if (empty($panel_readonly)): ?>
                    <div class="stock-modal-footer-danger">
                        <button type="button" class="btn btn-ghost-danger" id="btn-delete-product-from-stock">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Excluir produto do catálogo
                        </button>
                    </div>
                    <?php endif; ?>
                </footer>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$readonly = !empty($panel_readonly);
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script>'
    . '<script>window.panelStockReadonly = ' . ($readonly ? 'true' : 'false') . ';</script>'
    . '<script src="' . asset('js/panel-stock.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
