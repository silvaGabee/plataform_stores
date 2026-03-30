<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Funcionários</h1>
    <button type="button" class="btn btn-primary" id="btn-new-user">Novo funcionário</button>
    <div id="user-list"></div>
    <div id="user-modal" class="modal hidden">
        <div class="modal-content">
            <h2>Funcionário</h2>
            <form id="user-form">
                <input type="hidden" id="user-id">
                <label>Nome *</label>
                <input type="text" id="user-name" required>
                <label>E-mail *</label>
                <input type="email" id="user-email" required>
                <label>Senha <small>(deixe em branco para não alterar)</small></label>
                <input type="password" id="user-password">
                <label>Tipo</label>
                <select id="user-type">
                    <option value="funcionario">Funcionário</option>
                    <option value="gerente">Gerente</option>
                </select>
                <label>Cargo</label>
                <select id="user-role">
                    <option value="">Carregando cargos...</option>
                </select>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                    <button type="button" class="btn btn-sm" id="btn-delete-user" style="background:#dc2626;color:#fff;display:none">Excluir funcionário</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-users.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
