<?php
/*
 * Ajuda de cada indicador e gráfico: o botão "!" ao lado do título abre este
 * texto (passando o mouse, tocando no celular ou pelo teclado). Os textos ficam
 * em config/bi_ajuda.php, que o assistente de IA também usa para explicar o BI.
 */
$__ajuda = (require PLATAFORM_BACKEND . '/config/bi_ajuda.php')['secoes'];

/** Botão "!" + painel de ajuda de uma chave de $__ajuda. */
$__info = static function (string $chave) use ($__ajuda): string {
    [$titulo, $oQue, $serve, $como] = $__ajuda[$chave];
    $id = 'bi-ajuda-' . $chave;
    $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    return '<span class="bi-info-wrap">'
        . '<button type="button" class="bi-info" aria-expanded="false" aria-controls="' . $id . '" aria-label="Entender: ' . $h($titulo) . '"><span aria-hidden="true">!</span></button>'
        . '<span class="bi-info-pop" id="' . $id . '" role="region" aria-label="Sobre: ' . $h($titulo) . '" hidden>'
        . '<span class="bi-info-pop-head"><span class="bi-info-pop-title">' . $h($titulo) . '</span>'
        . '<button type="button" class="bi-info-close" aria-label="Fechar explicação">&times;</button></span>'
        . '<span class="bi-info-pop-item"><span class="bi-info-pop-label">O que é</span>' . $h($oQue) . '</span>'
        . '<span class="bi-info-pop-item"><span class="bi-info-pop-label">Para que serve</span>' . $h($serve) . '</span>'
        . '<span class="bi-info-pop-item"><span class="bi-info-pop-label">Como funciona</span>' . $h($como) . '</span>'
        . '</span></span>';
};

/** Cabeçalho de seção: título, botão "!", selo opcional e uma linha de resumo. */
$__cabecalho = static function (string $id, string $chave, string $resumo, string $selo = '') use ($__ajuda, $__info): string {
    $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    return '<div class="bi-section-head"><div class="bi-title-row">'
        . '<h3 id="' . $id . '" class="bi-section-title">' . $h($__ajuda[$chave][0]) . '</h3>'
        . $__info($chave)
        . ($selo !== '' ? '<span class="bi-badge">' . $h($selo) . '</span>' : '')
        . '</div><p class="bi-section-desc">' . $h($resumo) . '</p></div>';
};

/** Rótulo de KPI com o botão "!". */
$__kpiLabel = static function (string $chave) use ($__ajuda, $__info): string {
    return '<div class="bi-kpi-head"><h3 class="bi-kpi-label">' . htmlspecialchars($__ajuda[$chave][0]) . '</h3>' . $__info($chave) . '</div>';
};

ob_start();
?>
<div class="panel-content bi-page">
    <header class="bi-header">
        <div class="bi-header-text">
            <p class="bi-eyebrow">Inteligência da loja</p>
            <h1>Analyzing BI</h1>
            <p class="bi-subtitle">Vendas, lucro, estoque e decisões de compra num só lugar. Passe o mouse ou toque em <span class="bi-info-sample" aria-hidden="true">!</span> ao lado de cada indicador para entender o que ele mostra e como é calculado.</p>
        </div>
        <nav class="bi-jump" aria-label="Ir para a seção">
            <a href="#bi-grupo-geral">Visão geral</a>
            <a href="#bi-grupo-vendas">Vendas</a>
            <a href="#bi-grupo-produtos">Produtos</a>
            <a href="#bi-grupo-pagamentos">Pagamentos e cupons</a>
            <a href="#bi-grupo-estoque">Estoque</a>
            <a href="#bi-grupo-decisoes">Decisões</a>
        </nav>
    </header>

    <div id="bi-error" class="bi-msg bi-msg--error hidden" role="alert"></div>
    <div id="bi-loading" class="bi-loading">Carregando dados…</div>

    <div class="bi-group" id="bi-grupo-geral">
        <h2 class="bi-group-title">Visão geral</h2>
        <section id="bi-kpis" class="bi-kpis hidden" aria-label="Indicadores principais">
            <div class="bi-kpis-row bi-kpis-row--hero">
                <article class="bi-kpi bi-kpi--hero">
                    <?= $__kpiLabel('total') ?>
                    <p class="bi-kpi-value" id="bi-kpi-total">—</p>
                    <p class="bi-kpi-desc">Vendas pagas desde o início, sem o frete</p>
                </article>
                <article class="bi-kpi bi-kpi--hero">
                    <?= $__kpiLabel('mes') ?>
                    <p class="bi-kpi-value" id="bi-kpi-month">—</p>
                    <p class="bi-kpi-desc">Faturamento deste mês, sem o frete</p>
                </article>
                <article class="bi-kpi bi-kpi--hero bi-kpi--accent">
                    <?= $__kpiLabel('lucro') ?>
                    <p class="bi-kpi-value" id="bi-kpi-profit">—</p>
                    <p class="bi-kpi-desc">Vendas menos o custo das mercadorias</p>
                </article>
                <article class="bi-kpi bi-kpi--hero bi-kpi--accent">
                    <?= $__kpiLabel('lucro_mes') ?>
                    <p class="bi-kpi-value" id="bi-kpi-profit-month">—</p>
                    <p class="bi-kpi-desc">Faturamento do mês menos o custo do que foi vendido</p>
                    <p class="bi-kpi-desc" id="bi-kpi-margin"></p>
                </article>
            </div>
            <div class="bi-kpis-row bi-kpis-row--stats">
                <article class="bi-kpi">
                    <?= $__kpiLabel('pedidos') ?>
                    <p class="bi-kpi-value" id="bi-kpi-orders">—</p>
                    <p class="bi-kpi-desc">Pedidos com pagamento confirmado</p>
                </article>
                <article class="bi-kpi">
                    <?= $__kpiLabel('ticket') ?>
                    <p class="bi-kpi-value" id="bi-kpi-ticket">—</p>
                    <p class="bi-kpi-desc">Valor médio de cada pedido, sem o frete</p>
                </article>
                <article class="bi-kpi">
                    <?= $__kpiLabel('meta') ?>
                    <p class="bi-kpi-value" id="bi-kpi-goal">—</p>
                    <p class="bi-kpi-desc" id="bi-kpi-goal-desc"><a href="<?= base_url("painel/{$store['slug']}/metas") ?>">Definir meta</a></p>
                </article>
            </div>
        </section>
    </div>

    <div class="bi-group" id="bi-grupo-vendas">
        <h2 class="bi-group-title">Vendas</h2>
        <section id="bi-revenue-section" class="bi-revenue-section hidden" aria-labelledby="bi-revenue-title">
            <div class="bi-revenue-head">
                <div class="bi-revenue-head-text">
                    <?= $__cabecalho('bi-revenue-title', 'faturamento', 'Pedidos pagos, sem o frete. A linha tracejada é a previsão.') ?>
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
            <p id="bi-revenue-forecast" class="bi-revenue-forecast hidden" aria-live="polite"></p>
            <p id="bi-revenue-chart-error" class="bi-msg bi-msg--error hidden" role="alert"></p>
        </section>

        <section id="bi-chart-section" class="bi-chart-section hidden" aria-labelledby="bi-chart-title">
            <?= $__cabecalho('bi-chart-title', 'lucro_produto', 'Lucro de cada produto no mês, já com os descontos.', 'mês atual') ?>
            <div id="bi-chart-bars" class="bi-chart-root"></div>
            <p id="bi-chart-empty" class="bi-muted hidden">Sem dados para o gráfico: ainda não há vendas pagas com lucro positivo neste mês.</p>
        </section>
    </div>

    <div class="bi-group" id="bi-grupo-produtos">
        <h2 class="bi-group-title">Produtos</h2>
        <section id="bi-cards" class="bi-cards hidden" aria-label="Análises por produto">
            <article class="bi-card" id="bi-card-top">
                <div class="bi-title-row"><h3 class="bi-card-title">Produto mais vendido</h3><?= $__info('mais_vendido') ?><span class="bi-badge">mês atual</span></div>
                <div class="bi-card-body" id="bi-card-top-body">—</div>
            </article>
            <article class="bi-card" id="bi-card-bottom">
                <div class="bi-title-row"><h3 class="bi-card-title">Produto menos vendido</h3><?= $__info('menos_vendido') ?><span class="bi-badge">mês atual</span></div>
                <div class="bi-card-body" id="bi-card-bottom-body">—</div>
            </article>
            <article class="bi-card bi-card--alert" id="bi-card-stalled">
                <div class="bi-title-row"><h3 class="bi-card-title">Produtos parados</h3><?= $__info('parados') ?><span class="bi-badge bi-badge--warn">alerta</span></div>
                <div class="bi-card-body" id="bi-card-stalled-body">—</div>
            </article>
            <article class="bi-card bi-card--alert" id="bi-card-stock">
                <div class="bi-title-row"><h3 class="bi-card-title">Estoque crítico</h3><?= $__info('critico') ?></div>
                <div class="bi-card-body" id="bi-card-stock-body">—</div>
            </article>
        </section>

        <section id="bi-abc" class="bi-ideas hidden" aria-labelledby="bi-abc-title">
            <?= $__cabecalho('bi-abc-title', 'abc', 'Os poucos produtos que trazem a maior parte da receita.', '12 meses') ?>
            <div class="bi-pix-grid" id="bi-abc-resumo"></div>
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--compact">
                    <thead><tr><th>Produto</th><th>Classe</th><th class="ui-num">Faturamento</th><th class="ui-num">Acumulado</th><th class="ui-num">Margem</th><th class="ui-num">Markup</th></tr></thead>
                    <tbody id="bi-abc-rows"></tbody>
                </table>
            </div>
            <p class="bi-muted" id="bi-abc-note"></p>
        </section>
    </div>

    <div class="bi-group" id="bi-grupo-pagamentos">
        <h2 class="bi-group-title">Pagamentos e cupons</h2>
        <section id="bi-pix" class="bi-ideas bi-pix hidden" aria-labelledby="bi-pix-title">
            <?= $__cabecalho('bi-pix-title', 'pix', 'Quanto o desconto no PIX custa perto dos juros do cartão.', 'mês atual') ?>
            <div class="bi-pix-grid" id="bi-pix-body"></div>
        </section>

        <section id="bi-coupons" class="bi-ideas hidden" aria-labelledby="bi-coupons-title">
            <?= $__cabecalho('bi-coupons-title', 'cupons', 'Desconto dado em cupons e o que sobrou de receita e lucro.', '90 dias') ?>
            <div class="bi-pix-grid" id="bi-coupons-resumo"></div>
            <div class="ui-table-wrap" id="bi-coupons-table">
                <table class="ui-table ui-table--compact">
                    <thead><tr><th>Cupom</th><th class="ui-num">Pedidos</th><th class="ui-num">Desconto</th><th class="ui-num">Receita</th><th class="ui-num">Lucro</th><th class="ui-num">Margem</th></tr></thead>
                    <tbody id="bi-coupons-rows"></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="bi-group" id="bi-grupo-estoque">
        <h2 class="bi-group-title">Estoque</h2>
        <section id="bi-capital" class="bi-ideas bi-pix hidden" aria-labelledby="bi-capital-title">
            <?= $__cabecalho('bi-capital-title', 'capital', 'Dinheiro aplicado em mercadoria: estoque × custo.') ?>
            <div class="bi-pix-grid" id="bi-capital-body"></div>
        </section>

        <section id="bi-stock" class="bi-ideas hidden" aria-labelledby="bi-stock-title">
            <?= $__cabecalho('bi-stock-title', 'giro', 'Quanto o estoque gira e quando cada produto precisa de reposição.') ?>
            <div class="ui-seg" role="group" aria-label="Ordenar">
                <button type="button" class="ui-seg-btn is-active" data-ordem="repor">Repor primeiro</button>
                <button type="button" class="ui-seg-btn" data-ordem="capital">Mais dinheiro em estoque</button>
            </div>
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--compact">
                    <thead><tr><th>Produto</th><th class="ui-num">Estoque</th><th class="ui-num">Em custo</th><th class="ui-num">Giro</th><th>Cobertura</th><th>Repor até</th></tr></thead>
                    <tbody id="bi-stock-rows"></tbody>
                </table>
            </div>
            <p class="bi-muted" id="bi-stock-note"></p>
        </section>
    </div>

    <div class="bi-group" id="bi-grupo-decisoes">
        <h2 class="bi-group-title">Decisões</h2>
        <section id="bi-sim" class="bi-ideas hidden" aria-labelledby="bi-sim-title">
            <?= $__cabecalho('bi-sim-title', 'simulador', 'Simule a compra de um lote: VPL, TIR, payback e financiamento.', 'VPL · TIR · Price × SAC') ?>
            <form class="bi-sim-form" id="bi-sim-form" autocomplete="off">
                <label class="ui-field bi-sim-produto"><span>Produto</span><select name="produto" required></select></label>
                <label class="ui-field"><span>Quantidade</span><input name="quantidade" type="number" min="1" step="1" required></label>
                <label class="ui-field"><span>TMA (% ao mês)</span><input name="tma" type="number" min="0" max="20" step="0.01" value="1.5" required></label>
                <label class="ui-field"><span>Despesas sobre a venda (%)</span><input name="despesas" type="number" min="0" max="99.99" step="0.01" value="0" aria-describedby="bi-sim-despesas-hint"><small class="bi-muted" id="bi-sim-despesas-hint">Opcional: impostos, taxa do cartão, comissão. Reduz as entradas, não o ritmo de venda.</small></label>
                <label class="bi-sim-check"><input type="checkbox" name="estoque" checked> <span>O estoque atual vende primeiro</span></label>
                <fieldset class="bi-sim-fin">
                    <legend>Financiar a compra (opcional)</legend>
                    <label class="ui-field"><span>Parcelas</span>
                        <select name="parcelas">
                            <option value="0">Não, pagar à vista</option>
                            <?php foreach ([2, 3, 4, 6, 10, 12, 18, 24] as $n): ?><option value="<?= $n ?>"><?= $n ?>x</option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ui-field"><span>Juros (% ao mês)</span><input name="taxa" type="number" min="0" max="20" step="0.01" value="2"></label>
                </fieldset>
            </form>
            <p class="bi-muted" id="bi-sim-produto-info"></p>
            <div id="bi-sim-result" aria-live="polite"></div>
        </section>

        <section id="bi-ideas" class="bi-ideas hidden" aria-labelledby="bi-ideas-title">
            <?= $__cabecalho('bi-ideas-title', 'ideias', 'Sugestões automáticas a partir dos números acima.') ?>
            <ul id="bi-ideas-list" class="bi-ideas-list"></ul>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
$panel_main_extra_class = 'panel-main--bi-immersive';
$extra_css = '<link rel="stylesheet" href="' . asset('css/panel-bi.css') . '">';
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug'], JSON_UNESCAPED_UNICODE) . ';</script>'
    . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>'
    . '<script src="' . asset('js/panel-analyzing-bi.js') . '"></script>'
    . '<script src="' . asset('js/panel-bi-simulator.js') . '"></script>'
    . '<script src="' . asset('js/panel-bi-help.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
