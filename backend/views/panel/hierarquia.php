<?php $content = ob_start(); ?>
<div class="panel-content hierarchy-page">
    <header class="hierarchy-header">
        <div class="hierarchy-header-text">
            <p class="panel-page-eyebrow">Equipe</p>
            <h1>Hierarquia de cargos</h1>
            <p class="hierarchy-subtitle">Organize os cargos da loja, defina quem responde a quem e veja a estrutura no organograma.</p>
        </div>
        <?php if (empty($panel_readonly)): ?>
        <div class="hierarchy-header-actions">
            <button type="button" class="btn btn-secondary" id="btn-seed-example" title="Cria um organograma de exemplo (CEO, Comercial, Administrativo, Produção e subcargos). Cargos que já existem não são duplicados.">Gerar exemplo</button>
            <button type="button" class="btn btn-primary" id="btn-new-role">
                <?= btn_icon_plus() ?>
                Novo cargo
            </button>
        </div>
        <?php endif; ?>
    </header>
    <?php if (!empty($panel_readonly)): ?>
    <p class="panel-readonly-badge hierarchy-readonly-msg">Apenas visualização</p>
    <?php endif; ?>

    <div class="hierarchy-stats" id="hierarchy-stats" aria-live="polite">
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="roles">–</span><span class="hierarchy-stat-label">Cargos</span></div>
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="levels">–</span><span class="hierarchy-stat-label">Níveis</span></div>
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="people">–</span><span class="hierarchy-stat-label">Pessoas alocadas</span></div>
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="vacant">–</span><span class="hierarchy-stat-label">Cargos vagos</span></div>
    </div>

    <div class="hierarchy-layout">
        <section class="hierarchy-panel hierarchy-list-panel" aria-labelledby="hierarchy-list-title">
            <div class="hierarchy-panel-head">
                <h2 id="hierarchy-list-title">Cargos</h2>
            </div>
            <div class="hierarchy-search">
                <input type="search" id="role-search" placeholder="Buscar cargo ou pessoa…" aria-label="Buscar cargo ou pessoa" autocomplete="off">
            </div>
            <div id="role-list" class="hierarchy-role-list"></div>
        </section>

        <section class="hierarchy-panel hierarchy-org-panel" aria-labelledby="hierarchy-org-title">
            <div class="hierarchy-panel-head">
                <h2 id="hierarchy-org-title">Organograma</h2>
                <div class="org-toolbar" role="group" aria-label="Controles do organograma">
                    <button type="button" class="org-tool" data-org-action="expand" title="Expandir tudo">Expandir</button>
                    <button type="button" class="org-tool" data-org-action="collapse" title="Recolher abaixo do 2º nível">Recolher</button>
                    <span class="org-toolbar-sep" aria-hidden="true"></span>
                    <button type="button" class="org-tool org-tool-icon" data-org-action="zoom-out" title="Diminuir zoom" aria-label="Diminuir zoom">−</button>
                    <span class="org-zoom-value" id="org-zoom-value">100%</span>
                    <button type="button" class="org-tool org-tool-icon" data-org-action="zoom-in" title="Aumentar zoom" aria-label="Aumentar zoom">+</button>
                    <button type="button" class="org-tool" data-org-action="fit" title="Ajustar à largura">Ajustar</button>
                </div>
            </div>
            <div class="org-viewport" id="org-viewport">
                <div id="hierarchy-tree" class="org-canvas"></div>
            </div>
            <?php if (empty($panel_readonly)): ?>
            <p class="org-hint">Clique em um cargo para editar.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if (empty($panel_readonly)): ?>
    <div id="role-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="role-modal-title">Cargo</h2>
            <form id="role-form">
                <input type="hidden" id="role-id">
                <label for="role-name">Nome *</label>
                <input type="text" id="role-name" required>
                <label for="role-parent">Responde a</label>
                <select id="role-parent">
                    <option value="">Nenhum (topo da hierarquia)</option>
                </select>
                <label for="role-users">Quem está nesse cargo</label>
                <select id="role-users" multiple size="5" style="min-width:200px">
                    <option value="">Carregando...</option>
                </select>
                <small class="text-muted">Segure Ctrl (ou Cmd) para selecionar mais de uma pessoa.</small>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-hierarchy.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
