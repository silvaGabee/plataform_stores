<?php $content = ob_start(); ?>
<div class="panel-content ui-page" id="reviews-page" data-slug="<?= htmlspecialchars($store['slug']) ?>">
    <header class="panel-page-header ui-page-header">
        <div>
            <p class="panel-page-eyebrow">Vitrine</p>
            <h1>Avaliações</h1>
            <p class="panel-lead text-muted">Responda aos clientes e remova o que não deve ficar na vitrine. A resposta aparece em público, embaixo da avaliação. Remover tira a nota da página e da média do produto, e pode ser desfeito.</p>
        </div>
    </header>

    <div class="ui-stats" id="reviews-stats" aria-live="polite"></div>

    <div class="ui-card">
        <div class="ui-card-head">
            <h2 class="ui-card-title">Avaliações dos clientes</h2>
            <div class="ui-seg" role="group" aria-label="Mostrar">
                <button type="button" class="ui-seg-btn is-active" data-filtro="todas">Todas</button>
                <button type="button" class="ui-seg-btn" data-filtro="sem_resposta">A responder</button>
                <button type="button" class="ui-seg-btn" data-filtro="negativas">1 e 2 estrelas</button>
                <button type="button" class="ui-seg-btn" data-filtro="removidas">Removidas</button>
            </div>
        </div>
        <div class="reviews-mod-list" id="reviews-list"><p class="ui-empty">Carregando…</p></div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/panel-reviews.js') . '"></script>';
require PLATAFORM_BACKEND . '/views/panel/layout_panel.php';
