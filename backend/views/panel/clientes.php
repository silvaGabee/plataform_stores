<?php $content = ob_start(); ?>
<div class="panel-content ui-page" id="customers-page" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Vendas</p>
            <h1>Clientes</h1>
            <p class="panel-lead text-muted">Quem mais compra pela vitrine e quem parou de comprar. Só entram clientes com conta: a venda de balcão não identifica quem comprou.</p>
        </div>
        <div class="ui-page-actions">
            <a class="btn btn-secondary ui-btn-icon" href="<?= base_url("painel/{$store['slug']}/promocoes#cupons") ?>"><?= ui_icon('percent', 16) ?><span>Criar cupom</span></a>
        </div>
    </header>

    <div class="ui-stats" id="customers-stats" aria-live="polite"></div>

    <div class="ui-card ui-card--flush">
        <div class="ui-card-head ui-card-head--pad">
            <h2 class="ui-card-title">Clientes da vitrine</h2>
            <div class="ui-seg" role="group" aria-label="Mostrar">
                <button type="button" class="ui-seg-btn is-active" data-show="todos">Quem mais compra</button>
                <button type="button" class="ui-seg-btn" data-show="sumidos">Sumidos</button>
            </div>
        </div>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Cliente</th><th class="ui-num">Pedidos</th><th class="ui-num">Total comprado</th><th class="ui-num">Ticket médio</th><th>Última compra</th></tr></thead>
                <tbody id="customers-rows"><tr><td colspan="5" class="ui-empty">Carregando…</td></tr></tbody>
            </table>
        </div>
    </div>
    <p class="ui-hint" id="customers-note"></p>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-customers.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
