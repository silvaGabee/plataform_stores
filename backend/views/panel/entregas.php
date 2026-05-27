<?php $content = ob_start(); ?>
<div class="panel-content entregas-page">
    <h1>Entregas</h1>
    <p class="text-muted">Arraste os pedidos entre as colunas. Retirada só pode ir para Entregue. Em "Pedido em Rota" informe o código de rastreio. Na coluna <strong>Entregue</strong>, use <strong>Remover do histórico</strong> e então marque os pedidos que deseja apagar.</p>

    <div class="entregas-kanban">
        <section class="entregas-section entregas-retira">
            <h2>Retira na loja</h2>
            <div class="entregas-columns">
                <div class="entregas-col" data-type="retirada" data-stage="solicitado" data-droppable="true">
                    <h3>Solicitado</h3>
                    <div class="entregas-cards" id="retira-solicitado"></div>
                </div>
                <div class="entregas-col entregas-col--entregue" data-type="retirada" data-stage="entregue" data-droppable="true" data-entregue-col="retira-entregue">
                    <h3>Entregue</h3>
                    <div class="entregas-history-toolbar" data-toolbar-for="retira-entregue">
                        <div class="entregas-toolbar-idle" data-toolbar-idle="retira-entregue">
                            <button type="button" class="btn btn-sm btn-outline entregas-start-remove" data-start-remove-for="retira-entregue">Remover do histórico</button>
                        </div>
                        <div class="entregas-toolbar-remove hidden" data-toolbar-remove="retira-entregue">
                            <p class="entregas-remove-hint">Marque os pedidos que deseja remover:</p>
                            <span class="entregas-history-count" data-count-for="retira-entregue">Nenhum selecionado</span>
                            <div class="entregas-history-actions">
                                <button type="button" class="btn btn-sm btn-secondary entregas-select-all" data-select-all-for="retira-entregue">Selecionar todos</button>
                                <button type="button" class="btn btn-sm btn-outline entregas-cancel-remove" data-cancel-remove-for="retira-entregue">Cancelar</button>
                                <button type="button" class="btn btn-sm btn-danger entregas-confirm-remove" data-confirm-remove-for="retira-entregue" disabled>Confirmar remoção</button>
                            </div>
                        </div>
                    </div>
                    <div class="entregas-cards" id="retira-entregue"></div>
                </div>
            </div>
        </section>

        <section class="entregas-section entregas-entrega">
            <h2>Entregas solicitadas</h2>
            <div class="entregas-columns entregas-columns-5">
                <div class="entregas-col" data-type="entrega" data-stage="solicitado" data-droppable="true">
                    <h3>Solicitado</h3>
                    <div class="entregas-cards" id="entrega-solicitado"></div>
                </div>
                <div class="entregas-col" data-type="entrega" data-stage="empacotando" data-droppable="true">
                    <h3>Empacotando</h3>
                    <div class="entregas-cards" id="entrega-empacotando"></div>
                </div>
                <div class="entregas-col" data-type="entrega" data-stage="entregue_transportadora" data-droppable="true">
                    <h3>Entregue à transportadora</h3>
                    <div class="entregas-cards" id="entrega-transportadora"></div>
                </div>
                <div class="entregas-col" data-type="entrega" data-stage="em_rota" data-droppable="true">
                    <h3>Pedido em Rota</h3>
                    <div class="entregas-cards" id="entrega-em-rota"></div>
                </div>
                <div class="entregas-col entregas-col--entregue" data-type="entrega" data-stage="entregue" data-droppable="true" data-entregue-col="entrega-entregue">
                    <h3>Entregue</h3>
                    <div class="entregas-history-toolbar" data-toolbar-for="entrega-entregue">
                        <div class="entregas-toolbar-idle" data-toolbar-idle="entrega-entregue">
                            <button type="button" class="btn btn-sm btn-outline entregas-start-remove" data-start-remove-for="entrega-entregue">Remover do histórico</button>
                        </div>
                        <div class="entregas-toolbar-remove hidden" data-toolbar-remove="entrega-entregue">
                            <p class="entregas-remove-hint">Marque os pedidos que deseja remover:</p>
                            <span class="entregas-history-count" data-count-for="entrega-entregue">Nenhum selecionado</span>
                            <div class="entregas-history-actions">
                                <button type="button" class="btn btn-sm btn-secondary entregas-select-all" data-select-all-for="entrega-entregue">Selecionar todos</button>
                                <button type="button" class="btn btn-sm btn-outline entregas-cancel-remove" data-cancel-remove-for="entrega-entregue">Cancelar</button>
                                <button type="button" class="btn btn-sm btn-danger entregas-confirm-remove" data-confirm-remove-for="entrega-entregue" disabled>Confirmar remoção</button>
                            </div>
                        </div>
                    </div>
                    <div class="entregas-cards" id="entrega-entregue"></div>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="entregas-modal-tracking" class="modal hidden">
    <div class="modal-content">
        <h2>Código de rastreio</h2>
        <p>Informe o código da transportadora para o cliente acompanhar:</p>
        <input type="text" id="entregas-tracking-input" placeholder="Código de rastreio" class="full-width">
        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="entregas-tracking-confirm">Confirmar</button>
            <button type="button" class="btn btn-secondary close-entregas-modal">Cancelar</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$baseUrl = rtrim(base_url(), '/');
$extra_js = '<script>window.PANEL_BASE_URL = ' . json_encode($baseUrl) . '; const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-entregas.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
