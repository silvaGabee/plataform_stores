<?php $content = ob_start(); ?>
<div class="panel-content ui-page entregas-page" id="entregas" data-slug="<?= htmlspecialchars($store['slug']) ?>" data-gerente="<?= !empty($panel_is_gerente) ? '1' : '0' ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Pedidos da vitrine</p>
            <h1>Entregas</h1>
            <p class="panel-lead text-muted">Confirme pagamentos e acompanhe cada pedido até a entrega. Use <strong>Avançar</strong> no cartão (ou arraste no computador). As etapas só andam para a frente e só pedidos pagos entram no quadro.</p>
        </div>
        <div class="ui-page-actions">
            <button type="button" class="btn btn-secondary ui-btn-icon" id="ent-refresh"><?= ui_icon('history', 16) ?><span>Atualizar</span></button>
        </div>
    </header>

    <?php if (!empty($panel_is_gerente)): ?>
    <section class="ui-card" id="ent-await-wrap" aria-labelledby="ent-await-title">
        <div class="ui-card-head">
            <div>
                <h2 class="ui-card-title" id="ent-await-title"><?= ui_icon('clock', 18) ?> Aguardando pagamento <span class="ui-count" id="ent-await-count">0</span></h2>
                <p class="text-muted">PIX gerado na vitrine ou cartão a pagar na retirada. Confirme quando o valor entrar; pedidos não pagos no prazo são cancelados sozinhos e liberam o estoque reservado.</p>
            </div>
        </div>
        <div class="ui-await-list" id="ent-await"><p class="ui-empty">Carregando…</p></div>
    </section>
    <?php endif; ?>

    <section class="ui-board" aria-labelledby="ent-ret-title">
        <h2 class="ui-board-title" id="ent-ret-title"><?= ui_icon('store', 18) ?> Retirada na loja</h2>
        <div class="ui-board-cols ui-board-cols--2">
            <div class="ui-col" data-type="retirada" data-stage="solicitado"><h3>Aguardando retirada <span class="ui-count" data-count></span></h3><div class="ui-col-cards" data-cards></div></div>
            <div class="ui-col ui-col--done" data-type="retirada" data-stage="entregue"><h3>Retirado <span class="ui-col-note">últimos</span></h3><div class="ui-col-cards" data-cards></div></div>
        </div>
    </section>

    <section class="ui-board" aria-labelledby="ent-ent-title">
        <h2 class="ui-board-title" id="ent-ent-title"><?= ui_icon('truck', 18) ?> Entrega no endereço</h2>
        <div class="ui-board-cols ui-board-cols--5">
            <div class="ui-col" data-type="entrega" data-stage="solicitado"><h3>Solicitado <span class="ui-count" data-count></span></h3><div class="ui-col-cards" data-cards></div></div>
            <div class="ui-col" data-type="entrega" data-stage="empacotando"><h3>Empacotando <span class="ui-count" data-count></span></h3><div class="ui-col-cards" data-cards></div></div>
            <div class="ui-col" data-type="entrega" data-stage="entregue_transportadora"><h3>Na transportadora <span class="ui-count" data-count></span></h3><div class="ui-col-cards" data-cards></div></div>
            <div class="ui-col" data-type="entrega" data-stage="em_rota"><h3>Em rota <span class="ui-count" data-count></span></h3><div class="ui-col-cards" data-cards></div></div>
            <div class="ui-col ui-col--done" data-type="entrega" data-stage="entregue"><h3>Entregue <span class="ui-col-note">últimos</span></h3><div class="ui-col-cards" data-cards></div></div>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-entregas.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
