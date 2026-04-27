<?php $content = ob_start(); ?>
<div class="panel-content panel-employees">
    <header class="panel-employees-head">
        <div class="panel-employees-head-text">
            <p class="panel-page-eyebrow">Equipe</p>
            <h1>Funcionários</h1>
            <p class="panel-lead">Convide quem opera o painel com você. Gerentes têm acesso completo; funcionários seguem as permissões dos <a href="<?= base_url("painel/{$store['slug']}/hierarquia") ?>">cargos da hierarquia</a>.</p>
        </div>
        <button type="button" class="btn btn-primary panel-employees-add" id="btn-new-user">
            <svg class="panel-employees-add-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Novo funcionário
        </button>
    </header>
    <div id="user-list" class="panel-employees-list" aria-live="polite"></div>
    <div id="user-modal" class="modal hidden">
        <div class="modal-content modal-content--staff">
            <h2 id="user-modal-title">Funcionário</h2>
            <form id="user-form" class="panel-form-stack">
                <input type="hidden" id="user-id">
                <label for="user-name">Nome *</label>
                <input type="text" id="user-name" required autocomplete="name">
                <label for="user-email">E-mail *</label>
                <input type="email" id="user-email" required autocomplete="email">
                <label for="user-password">Senha <span class="panel-label-optional">(deixe em branco para não alterar)</span></label>
                <input type="password" id="user-password" autocomplete="new-password">
                <label for="user-type">Tipo</label>
                <select id="user-type">
                    <option value="funcionario">Funcionário</option>
                    <option value="gerente">Gerente</option>
                </select>
                <label for="user-role">Cargo</label>
                <select id="user-role">
                    <option value="">Carregando cargos...</option>
                </select>
                <div class="form-actions panel-employees-modal-actions">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger" id="btn-delete-user" hidden>Excluir funcionário</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '<script>const storeSlug = ' . json_encode($store['slug']) . ';</script><script src="' . asset('js/panel-users.js') . '"></script>';
require __DIR__ . '/layout_panel.php';
