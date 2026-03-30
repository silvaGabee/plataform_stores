<?php $content = ob_start(); ?>
<div class="panel-content entregas-page">
    <h1>Entregas</h1>
    <p class="text-muted">Arraste os pedidos entre as colunas. Retirada só pode ir para Entregue. Em "Pedido em Rota" informe o código de rastreio.</p>

    <div class="entregas-kanban">
        <section class="entregas-section entregas-retira">
            <h2>Retira na loja</h2>
            <div class="entregas-columns">
                <div class="entregas-col" data-type="retirada" data-stage="solicitado" data-droppable="true">
                    <h3>Solicitado</h3>
                    <div class="entregas-cards" id="retira-solicitado"></div>
                </div>
                <div class="entregas-col" data-type="retirada" data-stage="entregue" data-droppable="true">
                    <h3>Entregue</h3>
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
                <div class="entregas-col" data-type="entrega" data-stage="entregue" data-droppable="true">
                    <h3>Entregue</h3>
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
