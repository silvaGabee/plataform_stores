<?php $content = ob_start(); ?>
<div class="panel-content hierarchy-page">
    <h1>Hierarquia de cargos</h1>
    <?php if (!empty($panel_readonly)): ?>
    <p class="panel-readonly-badge hierarchy-readonly-msg">Apenas visualização</p>
    <?php endif; ?>
    <div class="hierarchy-layout">
        <div class="hierarchy-left">
            <?php if (empty($panel_readonly)): ?>
            <button type="button" class="btn btn-primary" id="btn-new-role">Novo cargo</button>
            <button type="button" class="btn btn-secondary" id="btn-seed-example" title="Cria o organograma de exemplo (CEO, Comercial, etc.). Depois você pode editar os nomes.">Organograma automático (Editável)</button>
            <?php endif; ?>
            <h2 class="hierarchy-list-title">Lista de cargos</h2>
            <div id="role-list"></div>
        </div>
        <div class="hierarchy-right">
            <h2 class="hierarchy-org-title">Organograma</h2>
            <div id="hierarchy-tree" class="hierarchy-tree"></div>
        </div>
    </div>
    <?php if (empty($panel_readonly)): ?>
    <div id="role-modal" class="modal hidden">
        <div class="modal-content">
            <h2>Cargo</h2>
            <form id="role-form">
                <input type="hidden" id="role-id">
                <label>Nome *</label>
                <input type="text" id="role-name" required>
                <label>Cargo superior</label>
                <select id="role-parent">
                    <option value="">Nenhum</option>
                </select>
                <label>Quem está nesse cargo</label>
                <select id="role-users" multiple size="4" style="min-width:200px">
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
