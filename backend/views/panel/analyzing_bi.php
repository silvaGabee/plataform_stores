<?php
ob_start();
?>
<div class="panel-content bi-page">
    <header class="bi-header">
        <div>
            <h1>Analyzing BI</h1>
            <p class="bi-subtitle">Indicadores de vendas, estoque e previsões simples para apoiar decisões na sua loja.</p>
        </div>
    </header>

    <div id="bi-error" class="bi-msg bi-msg--error hidden" role="alert"></div>
    <div id="bi-loading" class="bi-loading">Carregando dados…</div>

    <section id="bi-kpis" class="bi-kpis hidden" aria-label="Indicadores principais">
        <div class="bi-kpis-row bi-kpis-row--hero">
            <article class="bi-kpi bi-kpi--hero">
                <h2 class="bi-kpi-label">Valor total vendido</h2>
                <p class="bi-kpi-value" id="bi-kpi-total">—</p>
                <p class="bi-kpi-desc">Vendas totais da loja</p>
            </article>
            <article class="bi-kpi bi-kpi--hero">
                <h2 class="bi-kpi-label">Valor total do mês</h2>
                <p class="bi-kpi-value" id="bi-kpi-month">—</p>
                <p class="bi-kpi-desc">Faturamento só deste mês</p>
            </article>
        </div>
        <div class="bi-kpis-row bi-kpis-row--stats">
            <article class="bi-kpi">
                <h2 class="bi-kpi-label">Pedidos (pagos)</h2>
                <p class="bi-kpi-value" id="bi-kpi-orders">—</p>
                <p class="bi-kpi-desc">Quantidade de pedidos pagos</p>
            </article>
            <article class="bi-kpi">
                <h2 class="bi-kpi-label">Ticket médio</h2>
                <p class="bi-kpi-value" id="bi-kpi-ticket">—</p>
                <p class="bi-kpi-desc">Valor médio de cada pedido</p>
            </article>
            <article class="bi-kpi bi-kpi--accent">
                <h2 class="bi-kpi-label">Lucro estimado</h2>
                <p class="bi-kpi-value" id="bi-kpi-profit">—</p>
                <p class="bi-kpi-desc">Lucro aproximado das vendas</p>
            </article>
        </div>
    </section>

    <section id="bi-revenue-section" class="bi-revenue-section hidden" aria-labelledby="bi-revenue-title">
        <div class="bi-revenue-head">
            <div class="bi-revenue-head-text">
                <h2 id="bi-revenue-title" class="bi-section-title">Faturamento ao longo do tempo</h2>
                <p class="bi-section-desc bi-revenue-desc">Evolução do faturamento da loja (pedidos pagos ou enviados), só desta loja. Útil para ver crescimento, quedas e tendências.</p>
            </div>
            <div class="bi-revenue-filters" role="group" aria-label="Período do gráfico">
                <button type="button" class="bi-revenue-filter" data-periodo="7d">7 dias</button>
                <button type="button" class="bi-revenue-filter bi-revenue-filter--active" data-periodo="30d">30 dias</button>
                <button type="button" class="bi-revenue-filter" data-periodo="3m">3 meses</button>
            </div>
        </div>
        <div class="bi-revenue-chart-wrap">
            <canvas id="bi-revenue-chart" aria-label="Gráfico de linha: faturamento ao longo do tempo"></canvas>
        </div>
        <p id="bi-revenue-chart-error" class="bi-msg bi-msg--error hidden" role="alert"></p>
    </section>

    <section id="bi-chart-section" class="bi-chart-section hidden" aria-labelledby="bi-chart-title">
        <h2 id="bi-chart-title" class="bi-section-title">Possível venda do próximo mês</h2>
        <p class="bi-section-desc">Mostra, por produto, a quantidade prevista para o mês seguinte, com base na média das vendas nos últimos três meses (inclui o mês atual). Se ainda não houver histórico suficiente, usa as vendas recentes do mês atual.</p>
        <div id="bi-chart-bars" class="bi-chart-root"></div>
        <p id="bi-chart-empty" class="bi-muted hidden">Sem dados para o gráfico: ainda não há vendas pagas registadas por produto nesta loja.</p>
    </section>

    <section id="bi-cards" class="bi-cards hidden" aria-label="Análises por produto">
        <article class="bi-card" id="bi-card-top">
            <h2 class="bi-card-title">Produto mais vendido <span class="bi-badge">mês atual</span></h2>
            <div class="bi-card-body" id="bi-card-top-body">—</div>
        </article>
        <article class="bi-card" id="bi-card-bottom">
            <h2 class="bi-card-title">Produto menos vendido <span class="bi-badge">mês atual</span></h2>
            <div class="bi-card-body" id="bi-card-bottom-body">—</div>
        </article>
        <article class="bi-card bi-card--alert" id="bi-card-stalled">
            <h2 class="bi-card-title">Produtos parados <span class="bi-badge bi-badge--warn">alerta</span></h2>
            <div class="bi-card-body" id="bi-card-stalled-body">—</div>
        </article>
        <article class="bi-card bi-card--alert" id="bi-card-stock">
            <h2 class="bi-card-title">Estoque crítico</h2>
            <div class="bi-card-body" id="bi-card-stock-body">—</div>
        </article>
    </section>

    <section id="bi-ideas" class="bi-ideas hidden" aria-labelledby="bi-ideas-title">
        <h2 id="bi-ideas-title" class="bi-section-title">Ideias de investimento</h2>
        <p class="bi-section-desc">Sugestões automáticas com base nos números.</p>
        <ul id="bi-ideas-list" class="bi-ideas-list"></ul>
    </section>
</div>
<?php
$content = ob_get_clean();
$panel_main_extra_class = 'panel-main--bi-immersive';
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug'], JSON_UNESCAPED_UNICODE) . ';</script>'
    . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>'
    . '<script src="' . asset('js/panel-analyzing-bi.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
