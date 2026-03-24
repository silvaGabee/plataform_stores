<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Configurações</h1>
    <p class="text-muted">Preferências e ações sensíveis da loja <strong><?= htmlspecialchars($store['name']) ?></strong>.</p>

    <section class="panel-config-danger card" style="max-width: 560px; margin-top: 1.5rem; border-color: var(--danger-bg, #fee2e2);">
        <h2 class="panel-config-danger-title" style="margin-top: 0; font-size: 1.1rem; color: var(--danger, #dc2626);">Excluir loja</h2>
        <p class="text-muted" style="margin-bottom: 1rem;">
            Esta opção é <strong>irreversível</strong>. Depois de confirmada, todos os dados desta loja serão apagados de forma permanente
            (produtos, pedidos, clientes, funcionários, configurações e demais registros vinculados). Não é possível desfazer.
        </p>
        <button type="button" id="btn-show-delete-store" class="btn btn-secondary">Excluir loja</button>

        <div id="store-delete-confirm" class="hidden" style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border);">
            <p class="panel-config-delete-prompt" style="margin: 0 0 0.75rem 0; font-weight: 600; color: var(--text);">
                Digite <strong>Excluir</strong> no campo abaixo para confirmar.
            </p>
            <div class="checkout-field" style="max-width: 280px;">
                <label for="store-delete-confirmation-input">Confirmação</label>
                <input type="text" id="store-delete-confirmation-input" autocomplete="off" placeholder="Excluir" spellcheck="false">
            </div>
            <div class="form-actions" style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <button type="button" id="btn-confirm-delete-store" class="btn meus-enderecos-btn-delete">Confirmar exclusão da loja</button>
                <button type="button" id="btn-cancel-delete-store" class="btn btn-outline btn-sm">Cancelar</button>
            </div>
            <p id="store-delete-msg" class="text-muted" role="status" style="min-height: 1.25rem; margin-top: 0.75rem;"></p>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-configuracoes.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
