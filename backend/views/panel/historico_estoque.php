<?php $content = ob_start(); ?>
<div class="panel-content ui-page" id="stock-history" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Inventário</p>
            <h1>Histórico de estoque</h1>
            <p class="panel-lead text-muted">Toda entrada, venda e ajuste, com data, produto e quem fez. Visível apenas para gerentes.</p>
        </div>
        <div class="ui-page-actions">
            <a class="btn btn-secondary ui-btn-icon" id="sh-export" href="#"><?= ui_icon('download', 16) ?><span>Exportar CSV</span></a>
        </div>
    </header>

    <div class="ui-stats" aria-live="polite">
        <div class="ui-stat">
            <span class="ui-stat-label">Movimentações</span>
            <strong class="ui-stat-value" id="sh-total">—</strong>
        </div>
        <div class="ui-stat ui-stat--ok">
            <span class="ui-stat-label">Entradas (un.)</span>
            <strong class="ui-stat-value" id="sh-in">—</strong>
        </div>
        <div class="ui-stat ui-stat--info">
            <span class="ui-stat-label">Saídas por venda (un.)</span>
            <strong class="ui-stat-value" id="sh-out">—</strong>
        </div>
        <div class="ui-stat ui-stat--warn">
            <span class="ui-stat-label">Ajustes / baixas (un.)</span>
            <strong class="ui-stat-value" id="sh-adj">—</strong>
        </div>
    </div>

    <form class="ui-card ui-filters" id="sh-filters">
        <label class="ui-field ui-field--grow">
            <span>Buscar</span>
            <span class="ui-input-icon"><?= ui_icon('search', 16) ?><input type="search" name="q" placeholder="Produto ou motivo (ex.: Pedido #1200)"></span>
        </label>
        <label class="ui-field">
            <span>Tipo</span>
            <select name="type">
                <option value="">Todos</option>
                <option value="entrada">Entrada</option>
                <option value="saida">Saída (venda)</option>
                <option value="ajuste">Ajuste / baixa</option>
                <option value="devolucao">Devolução</option>
            </select>
        </label>
        <label class="ui-field">
            <span>Responsável</span>
            <select name="user_id" id="sh-users"><option value="">Todos</option></select>
        </label>
        <label class="ui-field">
            <span>De</span>
            <input type="date" name="from">
        </label>
        <label class="ui-field">
            <span>Até</span>
            <input type="date" name="to">
        </label>
        <div class="ui-field ui-field--actions">
            <button type="submit" class="btn btn-primary ui-btn-icon"><?= ui_icon('filter', 16) ?><span>Filtrar</span></button>
            <button type="reset" class="btn btn-secondary">Limpar</button>
        </div>
    </form>

    <section class="ui-card ui-card--flush">
        <div class="ui-table-wrap">
            <table class="ui-table" aria-describedby="sh-caption">
                <caption id="sh-caption" class="visually-hidden">Movimentações de estoque</caption>
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Tipo</th>
                        <th scope="col" class="ui-num">Qtd.</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Responsável</th>
                    </tr>
                </thead>
                <tbody id="sh-rows">
                    <tr><td colspan="6" class="ui-empty">Carregando…</td></tr>
                </tbody>
            </table>
        </div>
        <footer class="ui-pager">
            <span id="sh-range" class="text-muted"></span>
            <div class="ui-pager-btns">
                <button type="button" class="btn btn-secondary" id="sh-prev" disabled>Anterior</button>
                <button type="button" class="btn btn-secondary" id="sh-next" disabled>Próxima</button>
            </div>
        </footer>
    </section>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-stock-history.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
