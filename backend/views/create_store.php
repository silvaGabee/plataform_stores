<?php
ob_start();
?>
<main class="page create-store create-store-page">
    <header class="create-store-hero">
        <div class="create-store-hero-glow" aria-hidden="true"></div>
        <div class="container create-store-hero-inner">
            <div class="create-store-hero-copy">
                <p class="create-store-eyebrow">Nova loja</p>
                <h1 class="create-store-title">Criar <span class="create-store-title-accent">minha loja</span></h1>
                <p class="create-store-lead">Defina os dados da vitrine. A loja ficará associada à sua conta — confirme com a sua senha para validar.</p>
                <a href="<?= base_url('lojas') ?>" class="create-store-back js-create-store-back">Voltar às lojas</a>
            </div>
        </div>
    </header>

    <div class="container create-store-body">
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error create-store-alert">
                <strong>Erro:</strong> <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>

        <form method="post" action="<?= base_url('criar-loja') ?>" class="form-create-store create-store-form">
            <div class="create-store-form-grid">
                <section class="create-store-panel" aria-labelledby="create-store-loja-heading">
                    <h2 id="create-store-loja-heading" class="create-store-panel-title">Dados da loja</h2>
                    <p class="create-store-panel-desc">Informações que aparecem na vitrine e para os clientes.</p>
                    <div class="create-store-fields">
                        <div class="create-store-field">
                            <label for="cs-name">Nome da loja *</label>
                            <input type="text" id="cs-name" name="store_name" required value="<?= htmlspecialchars(old('store_name')) ?>" placeholder="Ex.: Minha Loja" autocomplete="organization">
                        </div>
                        <div class="create-store-field">
                            <label for="cs-category">Categoria</label>
                            <input type="text" id="cs-category" name="category" value="<?= htmlspecialchars(old('category')) ?>" placeholder="Ex.: Roupas, Alimentos" autocomplete="off">
                        </div>
                        <div class="create-store-fields-row">
                            <div class="create-store-field">
                                <label for="cs-city">Cidade</label>
                                <input type="text" id="cs-city" name="city" value="<?= htmlspecialchars(old('city')) ?>" placeholder="Ex.: São Paulo" autocomplete="address-level2">
                            </div>
                            <div class="create-store-field">
                                <label for="cs-phone">Telefone</label>
                                <input type="text" id="cs-phone" name="phone" value="<?= htmlspecialchars(old('phone')) ?>" placeholder="Ex.: (11) 99999-9999" autocomplete="tel">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="create-store-panel create-store-panel-account" aria-labelledby="create-store-gerente-heading">
                    <h2 id="create-store-gerente-heading" class="create-store-panel-title">A sua conta</h2>
                    <p class="create-store-panel-desc">O painel desta loja ficará ligado ao utilizador com sessão iniciada. Os dados abaixo vêm da sua conta e não podem ser alterados aqui.</p>
                    <?php
                    $cu = $current_user ?? [];
                    $cuName = htmlspecialchars((string) ($cu['name'] ?? ''));
                    $cuEmail = htmlspecialchars((string) ($cu['email'] ?? ''));
                    ?>
                    <div class="create-store-identity" aria-live="polite">
                        <div class="create-store-identity-block">
                            <span class="create-store-identity-label">Nome</span>
                            <p class="create-store-identity-value" id="create-store-identity-name"><?= $cuName !== '' ? $cuName : '—' ?></p>
                        </div>
                        <div class="create-store-identity-block">
                            <span class="create-store-identity-label">E-mail</span>
                            <p class="create-store-identity-value" id="create-store-identity-email"><?= $cuEmail !== '' ? $cuEmail : '—' ?></p>
                        </div>
                    </div>
                    <div class="create-store-fields">
                        <div class="create-store-field">
                            <label for="cs-manager-password">Confirme a sua senha *</label>
                            <input type="password" id="cs-manager-password" name="manager_password" required placeholder="Coloque a sua senha de login" autocomplete="current-password">
                            <p class="create-store-field-hint">Obrigatório para validar que é mesmo você. Tem de ser igual à senha utilizada no login.</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="create-store-form-footer">
                <button type="submit" class="btn btn-primary create-store-submit">Criar loja</button>
                <p class="create-store-form-hint">Ao criar, será redirecionado para a vitrine da nova loja.</p>
            </div>
        </form>
    </div>

    <div id="create-store-unsaved-dialog" class="create-store-unsaved-dialog" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="create-store-unsaved-title">
        <div class="create-store-unsaved-backdrop js-create-store-unsaved-cancel" tabindex="-1" aria-hidden="true"></div>
        <div class="create-store-unsaved-panel">
            <h2 id="create-store-unsaved-title" class="create-store-unsaved-title">Alterações não salvas</h2>
            <p class="create-store-unsaved-text">Você tem alterações não salvas.</p>
            <div class="create-store-unsaved-actions">
                <button type="button" class="btn btn-secondary js-create-store-unsaved-cancel">Cancelar</button>
                <button type="button" class="btn btn-primary js-create-store-unsaved-discard">Descartar</button>
            </div>
        </div>
    </div>
</main>
<?php
$extra_js = '<script src="' . asset('js/create-store.js') . '"></script>';
$content = ob_get_clean();
require __DIR__ . '/layout.php';
