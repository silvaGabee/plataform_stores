<?php $content = ob_start(); ?>
<div class="panel-content dashboard-builder-page">
    <h1>Meu dashboard</h1>
    <?php if (!empty($panel_readonly)): ?>
    <p class="panel-readonly-badge dashboard-readonly-msg">Apenas visualização</p>
    <?php endif; ?>
    <p class="dashboard-subtitle"><?= !empty($panel_readonly) ? 'Visualize os blocos do painel.' : 'Crie e organize os blocos do seu painel (como no Grafana ou Power BI).' ?></p>
    <div class="dashboard-toolbar">
        <div class="report-filters dashboard-filters">
            <label>De</label>
            <input type="date" id="report-from" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            <label>Até</label>
            <input type="date" id="report-to" value="<?= date('Y-m-d') ?>">
        </div>
        <?php if (empty($panel_readonly)): ?>
        <div class="dashboard-actions">
            <button type="button" class="btn btn-secondary" id="btn-edit-dashboard">Customizar dashboard</button>
            <div id="dashboard-edit-bar" class="dashboard-edit-bar hidden">
                <select id="widget-type">
                    <option value="">Adicionar bloco...</option>
                    <option value="revenue">Faturamento no período</option>
                    <option value="top_products">Produtos mais vendidos</option>
                    <option value="low_stock">Produtos com estoque baixo</option>
                    <option value="employees">Desempenho de funcionários</option>
                    <option value="goals">Metas (loja e por funcionário)</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" id="btn-add-widget">Adicionar</button>
                <button type="button" class="btn btn-primary" id="btn-save-layout">Salvar layout</button>
                <button type="button" class="btn btn-secondary" id="btn-cancel-edit">Concluir</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div id="dashboard-widgets" class="dashboard-widgets"></div>
    <div id="dashboard-empty" class="dashboard-empty hidden">
        <p>Nenhum bloco no dashboard.<?= empty($panel_readonly) ? ' Clique em <strong>Customizar dashboard</strong> e adicione blocos.' : '' ?></p>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-reports.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
