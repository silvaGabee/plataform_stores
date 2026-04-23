<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Configurações</h1>
    <p class="text-muted">Preferências e ações sensíveis da loja <strong><?= htmlspecialchars($store['name']) ?></strong>.</p>

    <section id="config-store-photo-section" class="panel-section-card panel-config-store-photo" aria-labelledby="config-store-photo-title">
        <div class="panel-section-head">
            <span class="panel-section-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="9" cy="10" r="1.5" fill="currentColor"/><path d="M20 15l-4-4-3 3-2-2-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="panel-section-head-text">
                <h2 id="config-store-photo-title" class="panel-section-title">Foto da loja</h2>
                <p class="panel-section-desc">Aparece na <strong>aba do navegador</strong>, no <strong>cabeçalho da vitrine</strong> e no <strong>painel da loja</strong>. Prefira imagem <strong>quadrada</strong> (logótipo ou ícone simples).</p>
            </div>
        </div>
        <div class="panel-section-body panel-config-store-photo-body">
            <div class="panel-config-store-photo-layout">
                <div class="panel-config-store-photo-visual">
                    <p class="panel-config-store-photo-visual-label">Pré-visualização</p>
                    <div id="config-store-photo-stage" class="panel-config-store-photo-stage">
                        <img id="config-store-photo-preview" src="" alt="" class="panel-config-store-photo-preview hidden" width="112" height="112" decoding="async">
                        <div id="config-store-photo-fallback" class="panel-config-store-photo-fallback" aria-hidden="true">
                            <span class="panel-config-store-photo-fallback-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.35" opacity="0.4"/><circle cx="9" cy="10" r="1.5" fill="currentColor" opacity="0.45"/><path d="M20 15l-4-4-3 3-2-2-5 5" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/></svg>
                            </span>
                            <span class="panel-config-store-photo-fallback-text">Sem foto</span>
                        </div>
                    </div>
                </div>
                <div class="panel-config-store-photo-main">
                    <ul class="panel-config-store-photo-tips" aria-label="Dicas">
                        <li>JPG, PNG, WebP ou ICO</li>
                        <li>Quadrado, pelo menos 48×48 px</li>
                    </ul>
                    <form id="config-store-photo-form" class="panel-config-store-photo-form" enctype="multipart/form-data">
                        <div class="panel-config-store-photo-drop">
                            <input type="file" id="config-store-photo-file" name="store_icon" class="config-store-photo-file-input" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp,.ico" title="Escolher imagem">
                            <label for="config-store-photo-file" class="panel-config-store-photo-browse">
                                <span class="panel-config-store-photo-browse-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </span>
                                Escolher imagem
                            </label>
                            <span id="config-store-photo-filename" class="panel-config-store-photo-filename">Nenhum ficheiro novo</span>
                        </div>
                        <div class="panel-config-store-photo-toolbar">
                            <button type="submit" class="btn btn-primary btn-sm panel-config-store-photo-btn-save">Guardar</button>
                            <button type="button" id="config-store-photo-remove" class="btn btn-secondary btn-sm hidden">Remover</button>
                        </div>
                    </form>
                    <p id="config-store-photo-msg" class="panel-form-msg panel-config-store-photo-msg" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>
    </section>

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
