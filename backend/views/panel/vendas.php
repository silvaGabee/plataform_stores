<?php $content = ob_start(); ?>
<div class="panel-content ui-page" id="sales-page" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Vendas</p>
            <h1>Vendas e estornos</h1>
            <p class="panel-lead text-muted">Vendas pagas do balcão e da vitrine. O estorno cancela a venda inteira, tira o valor da receita e do lucro do mês em que ela aconteceu e pode devolver as peças ao estoque.</p>
        </div>
    </header>

    <div class="ui-card">
        <div class="ui-filters">
            <label class="ui-field ui-field--grow"><span>Buscar</span><input type="search" id="sales-q" placeholder="Número do pedido ou nome do cliente" autocomplete="off"></label>
            <div class="ui-seg" role="group" aria-label="Situação">
                <button type="button" class="ui-seg-btn is-active" data-status="">Todas</button>
                <button type="button" class="ui-seg-btn" data-status="pago">Pagas</button>
                <button type="button" class="ui-seg-btn" data-status="estornado">Estornadas</button>
            </div>
        </div>
    </div>

    <div class="ui-card ui-card--flush">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Canal</th><th>Pagamento</th><th class="ui-num">Total</th><th>Situação</th><th></th></tr></thead>
                <tbody id="sales-rows"><tr><td colspan="8" class="ui-empty">Carregando…</td></tr></tbody>
            </table>
        </div>
    </div>
    <p class="ui-hint">Mostra as 100 vendas mais recentes que atendem ao filtro.</p>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-sales.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
