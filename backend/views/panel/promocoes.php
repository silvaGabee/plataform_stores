<?php
$content = ob_start();
$__rules = \App\Services\InstallmentPlan::rules($store);
$__pixPct = \App\Services\OrderPricing::pixPct($store);
?>
<div class="panel-content ui-page" id="promo-page" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Vendas</p>
            <h1>Promoções e pagamentos</h1>
            <p class="panel-lead text-muted">Preço promocional por produto, cupons da vitrine, parcelamento no cartão e os descontos pedidos no PDV. Só gerentes acessam.</p>
        </div>
    </header>

    <nav class="ui-tabs" role="tablist" aria-label="Seções">
        <button type="button" class="ui-tab is-active" role="tab" aria-selected="true" data-tab="produtos"><?= ui_icon('tag', 16) ?><span>Produtos em promoção</span></button>
        <button type="button" class="ui-tab" role="tab" aria-selected="false" data-tab="cupons"><?= ui_icon('percent', 16) ?><span>Cupons</span></button>
        <button type="button" class="ui-tab" role="tab" aria-selected="false" data-tab="parcelamento"><?= ui_icon('card', 16) ?><span>Parcelamento e PIX</span></button>
        <button type="button" class="ui-tab" role="tab" aria-selected="false" data-tab="frete"><?= ui_icon('truck', 16) ?><span>Frete</span></button>
        <button type="button" class="ui-tab" role="tab" aria-selected="false" data-tab="descontos"><?= ui_icon('shield', 16) ?><span>Descontos do PDV</span></button>
    </nav>

    <section class="ui-tabpanel is-active" data-panel="produtos">
        <div class="ui-card">
            <div class="ui-card-head">
                <div>
                    <h2 class="ui-card-title">Preço promocional</h2>
                    <p class="text-muted">O preço promocional aparece riscado ao lado do normal na vitrine e vale também no PDV. Deixe em branco para encerrar a promoção.</p>
                </div>
                <label class="ui-input-icon ui-search-inline"><?= ui_icon('search', 16) ?><input type="search" id="pp-search" placeholder="Filtrar produtos"></label>
            </div>
            <div class="ui-seg" role="group" aria-label="Mostrar">
                <button type="button" class="ui-seg-btn is-active" data-show="todos">Todos</button>
                <button type="button" class="ui-seg-btn" data-show="promo">Em promoção</button>
            </div>
        </div>
        <div class="ui-card ui-card--flush">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Produto</th><th class="ui-num">Custo</th><th class="ui-num">Preço normal</th><th>Preço promocional</th><th class="ui-num">Desconto</th><th class="ui-num">Margem</th><th></th></tr></thead>
                    <tbody id="pp-rows"><tr><td colspan="7" class="ui-empty">Carregando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ui-tabpanel" data-panel="cupons" hidden>
        <div class="ui-grid-2">
            <form class="ui-card ui-form" id="cp-form">
                <h2 class="ui-card-title">Novo cupom</h2>
                <label class="ui-field"><span>Código</span><input name="code" required maxlength="30" placeholder="EX.: BEMVINDO10" autocomplete="off" style="text-transform:uppercase"></label>
                <div class="ui-row">
                    <label class="ui-field"><span>Tipo</span>
                        <select name="type"><option value="percent">Percentual (%)</option><option value="valor">Valor fixo (R$)</option></select>
                    </label>
                    <label class="ui-field"><span>Desconto</span><input name="value" type="number" step="0.01" min="0.01" required placeholder="10"></label>
                </div>
                <div class="ui-row">
                    <label class="ui-field"><span>Compra mínima (R$)</span><input name="min_subtotal" type="number" step="0.01" min="0" placeholder="0,00"></label>
                    <label class="ui-field"><span>Limite de usos</span><input name="max_uses" type="number" min="0" placeholder="Sem limite"></label>
                </div>
                <label class="ui-field"><span>Válido até</span><input name="ends_at" type="date"></label>
                <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('plus', 16) ?><span>Criar cupom</span></button>
                <p class="ui-hint">O desconto de um cupom é limitado a 90% da compra.</p>
            </form>
            <div class="ui-card ui-card--flush">
                <div class="ui-card-head ui-card-head--pad"><h2 class="ui-card-title">Cupons da loja</h2></div>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Código</th><th>Desconto</th><th>Regras</th><th class="ui-num">Usos</th><th>Situação</th><th></th></tr></thead>
                        <tbody id="cp-rows"><tr><td colspan="6" class="ui-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="ui-tabpanel" data-panel="parcelamento" hidden>
        <div class="ui-grid-2">
            <div class="ui-stack">
            <form class="ui-card ui-form" id="ip-form">
                <h2 class="ui-card-title">Regra de parcelamento no cartão</h2>
                <p class="text-muted">Vale para pedidos da vitrine pagos com cartão na entrega. Acima das parcelas sem juros, o valor da parcela segue a tabela Price com a taxa mensal informada.</p>
                <div class="ui-row">
                    <label class="ui-field"><span>Máximo de parcelas</span><input name="max" type="number" min="1" max="12" value="<?= (int) $__rules['max'] ?>" required></label>
                    <label class="ui-field"><span>Sem juros até</span><input name="free" type="number" min="1" max="12" value="<?= (int) $__rules['free'] ?>" required></label>
                </div>
                <div class="ui-row">
                    <label class="ui-field"><span>Juros ao mês (%)</span><input name="rate" type="number" step="0.01" min="0" max="15" value="<?= htmlspecialchars(number_format($__rules['rate'], 2, '.', '')) ?>" required></label>
                    <label class="ui-field"><span>Parcela mínima (R$)</span><input name="min_value" type="number" step="0.01" min="0" value="<?= htmlspecialchars(number_format($__rules['min_value'], 2, '.', '')) ?>" required></label>
                </div>
                <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('check', 16) ?><span>Salvar regra</span></button>
            </form>
            <form class="ui-card ui-form" id="pix-form">
                <h2 class="ui-card-title">Desconto à vista no PIX</h2>
                <p class="text-muted">Quem paga a compra online no PIX ganha este desconto sobre os produtos (o frete não entra). A vitrine mostra o preço no PIX em cada produto. Use 0 para desligar.</p>
                <label class="ui-field"><span>Desconto no PIX (%)</span><input name="pct" type="number" step="0.5" min="0" max="<?= (int) \App\Services\OrderPricing::PIX_MAX_PCT ?>" value="<?= htmlspecialchars(number_format($__pixPct, 2, '.', '')) ?>" required></label>
                <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('check', 16) ?><span>Salvar desconto</span></button>
                <div class="ui-callout" id="pix-equivalencia" aria-live="polite"></div>
            </form>
            </div>
            <div class="ui-card">
                <div class="ui-card-head">
                    <h2 class="ui-card-title">Simulação</h2>
                    <label class="ui-field ui-field--inline"><span>Compra de</span><input id="ip-sim-value" type="number" step="0.01" min="1" value="300"></label>
                </div>
                <div class="ui-table-wrap">
                    <table class="ui-table ui-table--compact">
                        <thead><tr><th>Parcelas</th><th class="ui-num">Parcela</th><th class="ui-num">Total</th><th class="ui-num">Juros</th></tr></thead>
                        <tbody id="ip-sim"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="ui-tabpanel" data-panel="frete" hidden>
        <div class="ui-grid-2">
            <div class="ui-stack">
                <form class="ui-card ui-form" id="fr-form">
                    <h2 class="ui-card-title">Nova faixa de CEP</h2>
                    <p class="text-muted">O cliente paga o frete da faixa que contém o CEP dele. Se um CEP cair em duas faixas, vale a menor (ex.: um bairro dentro da cidade). Sem nenhuma faixa, a entrega sai sem custo; com faixas, CEP fora delas não recebe entrega.</p>
                    <label class="ui-field"><span>Nome</span><input name="name" required maxlength="80" placeholder="Ex.: Belo Horizonte"></label>
                    <div class="ui-row">
                        <label class="ui-field"><span>CEP inicial</span><input name="cep_start" required inputmode="numeric" maxlength="9" placeholder="30000-000"></label>
                        <label class="ui-field"><span>CEP final</span><input name="cep_end" required inputmode="numeric" maxlength="9" placeholder="31999-999"></label>
                    </div>
                    <div class="ui-row">
                        <label class="ui-field"><span>Valor (R$)</span><input name="price" type="number" step="0.01" min="0" required placeholder="9,90"></label>
                        <label class="ui-field"><span>Prazo (dias úteis)</span><input name="days" type="number" min="0" max="120" placeholder="Opcional"></label>
                    </div>
                    <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('plus', 16) ?><span>Adicionar faixa</span></button>
                </form>
                <form class="ui-card ui-form" id="fr-free-form">
                    <h2 class="ui-card-title">Frete grátis</h2>
                    <label class="ui-field"><span>Grátis em compras a partir de (R$)</span><input name="free_shipping_min" type="number" step="0.01" min="0" placeholder="Deixe vazio para não oferecer"></label>
                    <button type="submit" class="btn btn-secondary ui-btn-icon"><?= ui_icon('check', 16) ?><span>Salvar</span></button>
                </form>
            </div>
            <div class="ui-card ui-card--flush">
                <div class="ui-card-head ui-card-head--pad"><h2 class="ui-card-title">Faixas cadastradas</h2></div>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Faixa</th><th>CEPs</th><th class="ui-num">Valor</th><th class="ui-num">Prazo</th><th></th></tr></thead>
                        <tbody id="fr-rows"><tr><td colspan="5" class="ui-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
                <div class="ui-card-foot">
                    <label class="ui-field ui-field--inline"><span>Testar CEP</span><input id="fr-test-cep" inputmode="numeric" maxlength="9" placeholder="00000-000"></label>
                    <p class="ui-hint" id="fr-test-result" aria-live="polite"></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ui-tabpanel" data-panel="descontos" hidden>
        <div class="ui-card ui-card--flush">
            <div class="ui-card-head ui-card-head--pad">
                <div>
                    <h2 class="ui-card-title">Descontos solicitados no PDV</h2>
                    <p class="text-muted">Pedidos pendentes aparecem em qualquer tela do painel para você aprovar ou recusar. Aqui fica o registro das decisões.</p>
                </div>
            </div>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Data</th><th>Funcionário</th><th class="ui-num">Venda</th><th class="ui-num">Desconto</th><th>Motivo</th><th>Situação</th><th>Decidido por</th></tr></thead>
                    <tbody id="dr-rows"><tr><td colspan="7" class="ui-empty">Carregando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-promotions.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
