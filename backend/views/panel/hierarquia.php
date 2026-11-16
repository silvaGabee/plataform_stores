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
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="people">–</span><span class="hierarchy-stat-label">Pessoas na equipe</span></div>
        <button type="button" class="hierarchy-stat hierarchy-stat-link" data-people-filter="none" title="Ver quem está sem cargo"><span class="hierarchy-stat-value" data-stat="unassigned">–</span><span class="hierarchy-stat-label">Sem cargo</span></button>
        <button type="button" class="hierarchy-stat hierarchy-stat-link" data-people-filter="multi" title="Ver quem tem mais de um cargo"><span class="hierarchy-stat-value" data-stat="multi">–</span><span class="hierarchy-stat-label">Com 2+ cargos</span></button>
        <div class="hierarchy-stat"><span class="hierarchy-stat-value" data-stat="vacant">–</span><span class="hierarchy-stat-label">Cargos vagos</span></div>
    </div>

    <div class="hierarchy-layout">
        <section class="hierarchy-panel hierarchy-list-panel" aria-labelledby="hierarchy-list-title">
            <h2 id="hierarchy-list-title" class="visually-hidden">Cargos e pessoas</h2>
            <div class="hierarchy-tabs" role="tablist" aria-label="Ver por">
                <button type="button" class="hierarchy-tab is-active" role="tab" id="tab-roles" aria-controls="role-list" aria-selected="true" data-tab="roles">Cargos <span class="hierarchy-tab-count" data-count="roles"></span></button>
                <button type="button" class="hierarchy-tab" role="tab" id="tab-people" aria-controls="people-pane" aria-selected="false" data-tab="people">Pessoas <span class="hierarchy-tab-count" data-count="people"></span></button>
            </div>
            <div class="hierarchy-search">
                <input type="search" id="role-search" placeholder="Buscar cargo ou pessoa…" aria-label="Buscar cargo ou pessoa" autocomplete="off">
            </div>
            <div id="role-list" class="hierarchy-role-list" role="tabpanel" aria-labelledby="tab-roles"></div>
            <div id="people-pane" role="tabpanel" aria-labelledby="tab-people" hidden>
                <div class="people-filters" role="group" aria-label="Filtrar pessoas">
                    <button type="button" class="people-chip is-active" data-people-filter="all">Todas <span data-count="all"></span></button>
                    <button type="button" class="people-chip people-chip-warning" data-people-filter="none">Sem cargo <span data-count="none"></span></button>
                    <button type="button" class="people-chip people-chip-info" data-people-filter="multi">2+ cargos <span data-count="multi"></span></button>
                </div>
                <div id="people-list" class="hierarchy-role-list"></div>
            </div>
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
    <div id="person-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="person-modal-title">Cargos da pessoa</h2>
            <form id="person-form">
                <input type="hidden" id="person-id">
                <p class="person-modal-lead">Marque um ou mais cargos. Quem acumula funções pode ficar em mais de um.</p>
                <div id="person-roles" class="person-role-options"></div>
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
