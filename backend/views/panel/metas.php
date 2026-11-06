<?php
$content = ob_start();
$__gerente = !empty($panel_is_gerente);
?>
<div class="panel-content ui-page" id="goals-page" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Vendas</p>
            <h1>Metas</h1>
            <p class="panel-lead text-muted"><?= $__gerente
                ? 'Defina a meta de faturamento do mês, divida entre os funcionários e acompanhe quanto já foi vendido.'
                : 'Acompanhe a sua meta do mês e quanto você já vendeu.' ?></p>
        </div>
        <label class="ui-field ui-field--inline goals-period"><span>Mês</span><input type="month" id="goals-period" value="<?= htmlspecialchars(date('Y-m')) ?>" required></label>
    </header>

    <div class="ui-stats" id="goals-stats" aria-live="polite"></div>
    <?php if ($__gerente): ?><p class="ui-hint" id="goals-sources"></p><?php endif; ?>

    <?php if ($__gerente): ?>
    <div class="ui-stack">
        <form class="ui-card ui-form" id="goals-store-form">
            <h2 class="ui-card-title">Meta da loja</h2>
            <p class="text-muted">Inclui todas as vendas pagas do mês, no balcão e na vitrine, sem o frete.</p>
            <label class="ui-field"><span>Meta do mês (R$)</span><input name="goal_amount" type="number" step="0.01" min="0" required placeholder="0,00"></label>
            <label class="goals-check"><input type="checkbox" name="distribute"> <span>Dividir igualmente entre os funcionários</span></label>
            <div class="ui-form" id="goals-split" hidden>
                <label class="ui-field"><span>Quanto dividir entre eles (R$)</span><input name="distribute_amount" type="number" step="0.01" min="0.01" placeholder="0,00"></label>
                <p class="ui-hint" id="goals-split-hint"></p>
                <p class="ui-hint">A divisão substitui as metas individuais deste mês. A vitrine e as vendas do gerente não contam para nenhum funcionário, então a parte deles fica fora da divisão.</p>
            </div>
            <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('check', 16) ?><span>Salvar meta</span></button>
        </form>
        <div class="ui-card ui-card--flush">
            <div class="ui-card-head ui-card-head--pad">
                <div>
                    <h2 class="ui-card-title">Funcionários</h2>
                    <p class="text-muted" id="goals-team-sum"></p>
                </div>
            </div>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Funcionário</th><th class="ui-num">Vendas</th><th class="ui-num">Vendido</th><th>Atingido</th><th>Meta (R$)</th><th></th></tr></thead>
                    <tbody id="goals-rows"><tr><td colspan="6" class="ui-empty">Carregando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-goals.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
