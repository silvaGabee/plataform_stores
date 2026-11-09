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
                <p class="bi-kpi-desc">Vendas totais da loja, sem o frete</p>
            </article>
            <article class="bi-kpi bi-kpi--hero">
                <h2 class="bi-kpi-label">Valor total do mês</h2>
                <p class="bi-kpi-value" id="bi-kpi-month">—</p>
                <p class="bi-kpi-desc">Faturamento só deste mês, sem o frete</p>
            </article>
            <article class="bi-kpi bi-kpi--hero bi-kpi--accent">
                <h2 class="bi-kpi-label">Lucro total estimado</h2>
                <p class="bi-kpi-value" id="bi-kpi-profit">—</p>
                <p class="bi-kpi-desc">Lucro de todas as vendas da loja</p>
            </article>
            <article class="bi-kpi bi-kpi--hero bi-kpi--accent">
                <h2 class="bi-kpi-label">Lucro estimado do mês</h2>
                <p class="bi-kpi-value" id="bi-kpi-profit-month">—</p>
                <p class="bi-kpi-desc">Faturamento do mês menos o custo do que foi vendido</p>
                <p class="bi-kpi-desc" id="bi-kpi-margin"></p>
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
                <p class="bi-kpi-desc">Valor médio de cada pedido, sem o frete</p>
            </article>
            <article class="bi-kpi">
                <h2 class="bi-kpi-label">Meta do mês</h2>
                <p class="bi-kpi-value" id="bi-kpi-goal">—</p>
                <p class="bi-kpi-desc" id="bi-kpi-goal-desc"><a href="<?= base_url("painel/{$store['slug']}/metas") ?>">Definir meta</a></p>
            </article>
        </div>
    </section>

    <section id="bi-revenue-section" class="bi-revenue-section hidden" aria-labelledby="bi-revenue-title">
        <div class="bi-revenue-head">
            <div class="bi-revenue-head-text">
                <h2 id="bi-revenue-title" class="bi-section-title">Faturamento ao longo do tempo</h2>
                <p class="bi-section-desc bi-revenue-desc">Evolução do faturamento da loja (pedidos pagos, sem o frete), só desta loja. Útil para ver crescimento, quedas e tendências.</p>
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
        <h2 id="bi-chart-title" class="bi-section-title">Lucro por produto</h2>
        <p class="bi-section-desc">Lucro do mês atual por produto: (preço de venda − custo na data da venda) × quantidade vendida em pedidos pagos, já descontados os descontos concedidos. Ex.: custo R$ 4, venda R$ 6 → margem R$ 2; com 10 unidades vendidas, lucro R$ 20.</p>
        <div id="bi-chart-bars" class="bi-chart-root"></div>
        <p id="bi-chart-empty" class="bi-muted hidden">Sem dados para o gráfico: ainda não há vendas pagas com lucro positivo neste mês.</p>
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

    <section id="bi-pix" class="bi-ideas bi-pix hidden" aria-labelledby="bi-pix-title">
        <h2 id="bi-pix-title" class="bi-section-title">PIX à vista × parcelamento <span class="bi-badge">mês atual</span></h2>
        <p class="bi-section-desc">Quanto a loja abriu mão com o desconto no PIX, quanto recebeu de juros no cartão e o desconto expresso como taxa de juros ao mês.</p>
        <div class="bi-pix-grid" id="bi-pix-body"></div>
    </section>

    <section id="bi-capital" class="bi-ideas bi-pix hidden" aria-labelledby="bi-capital-title">
        <h2 id="bi-capital-title" class="bi-section-title">Capital em estoque</h2>
        <p class="bi-section-desc">Quanto dinheiro está em mercadoria: estoque × custo atual de cada produto.</p>
        <div class="bi-pix-grid" id="bi-capital-body"></div>
    </section>

    <section id="bi-abc" class="bi-ideas hidden" aria-labelledby="bi-abc-title">
        <h2 id="bi-abc-title" class="bi-section-title">Curva ABC <span class="bi-badge">12 meses</span></h2>
        <p class="bi-section-desc">Produtos ordenados pelo faturamento. Classe A: os primeiros, até somar 80% da receita; B: até 95%; C: o restante. Margem é o lucro sobre a venda; markup, o lucro sobre o custo.</p>
        <div class="bi-pix-grid" id="bi-abc-resumo"></div>
        <div class="ui-table-wrap">
            <table class="ui-table ui-table--compact">
                <thead><tr><th>Produto</th><th>Classe</th><th class="ui-num">Faturamento</th><th class="ui-num">Acumulado</th><th class="ui-num">Margem</th><th class="ui-num">Markup</th></tr></thead>
                <tbody id="bi-abc-rows"></tbody>
            </table>
        </div>
        <p class="bi-muted" id="bi-abc-note"></p>
    </section>

    <section id="bi-stock" class="bi-ideas hidden" aria-labelledby="bi-stock-title">
        <h2 id="bi-stock-title" class="bi-section-title">Giro e cobertura de estoque</h2>
        <p class="bi-section-desc">A cobertura projeta quantos dias o estoque dura no ritmo de venda dos últimos 90 dias. "Repor até" é quando ele chega ao mínimo; desconte o prazo do seu fornecedor. O giro é o custo do vendido em 12 meses ÷ o estoque atual a custo.</p>
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

    <section id="bi-coupons" class="bi-ideas hidden" aria-labelledby="bi-coupons-title">
        <h2 id="bi-coupons-title" class="bi-section-title">Cupons <span class="bi-badge">90 dias</span></h2>
        <p class="bi-section-desc">Quanto foi dado de desconto e o que sobrou de receita e lucro, em compras pagas da vitrine. A comparação mostra a diferença entre os dois grupos, não quanto o cupom fez vender a mais: quem usou cupom talvez comprasse de qualquer jeito.</p>
        <div class="bi-pix-grid" id="bi-coupons-resumo"></div>
        <div class="ui-table-wrap" id="bi-coupons-table">
            <table class="ui-table ui-table--compact">
                <thead><tr><th>Cupom</th><th class="ui-num">Pedidos</th><th class="ui-num">Desconto</th><th class="ui-num">Receita</th><th class="ui-num">Lucro</th><th class="ui-num">Margem</th></tr></thead>
                <tbody id="bi-coupons-rows"></tbody>
            </table>
        </div>
    </section>

    <section id="bi-sim" class="bi-ideas hidden" aria-labelledby="bi-sim-title">
        <h2 id="bi-sim-title" class="bi-section-title">Vale a pena comprar este estoque? <span class="bi-badge">VPL · TIR · Price × SAC</span></h2>
        <p class="bi-section-desc">Simula a compra de um lote: o dinheiro sai hoje e volta conforme as peças vendem, no ritmo dos últimos 90 dias e pelo preço médio que a loja de fato recebeu. A TMA é quanto o dinheiro da loja renderia em outro uso, ao mês. Nada é gravado.</p>
        <form class="bi-sim-form" id="bi-sim-form" autocomplete="off">
            <label class="ui-field bi-sim-produto"><span>Produto</span><select name="produto" required></select></label>
            <label class="ui-field"><span>Quantidade</span><input name="quantidade" type="number" min="1" step="1" required></label>
            <label class="ui-field"><span>TMA (% ao mês)</span><input name="tma" type="number" min="0" max="20" step="0.01" value="1.5" required></label>
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
    . '<script src="' . asset('js/panel-analyzing-bi.js') . '"></script>'
    . '<script src="' . asset('js/panel-bi-simulator.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
