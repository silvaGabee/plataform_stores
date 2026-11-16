<?php
/**
 * Entrar / criar conta dentro da loja.
 *
 * Mesmo cabeçalho e mesmas cores da vitrine: o cliente que tocou em
 * "Finalizar compra" continua vendo a marca da loja, e não a página da
 * plataforma com texto escrito para lojistas.
 *
 * Os formulários enviam para /login e /criar-conta com o campo "origem"; o
 * HomeController devolve os erros para esta tela.
 *
 * @var array $store
 * @var string $modo login|cadastro
 */
$__info = (string) ($_SESSION['_info'] ?? '');
$__erro = (string) ($_SESSION['_error'] ?? '');
$__ok = (string) ($_SESSION['_success'] ?? '');
unset($_SESSION['_info'], $_SESSION['_error'], $_SESSION['_success']);
$__cadastro = $modo === 'cadastro';
$__entrarUrl = base_url("loja/{$store['slug']}/entrar");
ob_start();
?>
<div class="store-auth-page">
    <div class="container store-auth-inner">
        <section class="store-auth-card" aria-labelledby="store-auth-title">
            <header class="store-auth-head">
                <img src="<?= htmlspecialchars(store_brand_icon_url($store)) ?>" alt="" class="store-auth-icon" width="48" height="48" decoding="async">
                <h1 id="store-auth-title" class="store-auth-title"><?= $__cadastro ? 'Criar sua conta' : 'Entrar na sua conta' ?></h1>
                <p class="store-auth-lead">
                    <?= $__cadastro
                        ? 'Leva menos de um minuto. Seu carrinho continua guardado.'
                        : 'Use o e-mail e a senha da sua conta para continuar comprando na ' . htmlspecialchars($store['name']) . '.' ?>
                </p>
            </header>

            <nav class="store-auth-tabs" aria-label="Entrar ou criar conta">
                <a href="<?= htmlspecialchars($__entrarUrl) ?>" class="store-auth-tab<?= $__cadastro ? '' : ' is-active' ?>"<?= $__cadastro ? '' : ' aria-current="page"' ?>>Entrar</a>
                <a href="<?= htmlspecialchars($__entrarUrl . '?modo=cadastro') ?>" class="store-auth-tab<?= $__cadastro ? ' is-active' : '' ?>"<?= $__cadastro ? ' aria-current="page"' : '' ?>>Criar conta</a>
            </nav>

            <?php if ($__info !== ''): ?>
                <p class="store-auth-notice store-auth-notice--info" role="status"><?= ui_icon('info', 18) ?><span><?= htmlspecialchars($__info) ?></span></p>
            <?php endif; ?>
            <?php if ($__ok !== ''): ?>
                <p class="store-auth-notice store-auth-notice--ok" role="status"><?= ui_icon('check', 18) ?><span><?= htmlspecialchars($__ok) ?></span></p>
            <?php endif; ?>
            <?php if ($__erro !== ''): ?>
                <p class="store-auth-notice store-auth-notice--error" role="alert"><?= ui_icon('alert', 18) ?><span><?= htmlspecialchars($__erro) ?></span></p>
            <?php endif; ?>

            <?php if (!$__cadastro): ?>
                <form method="post" action="<?= base_url('login') ?>" class="store-auth-form" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="auth_intent" value="login">
                    <input type="hidden" name="origem" value="<?= htmlspecialchars($store['slug']) ?>">
                    <div class="ui-form-field">
                        <label for="store-login-email">E-mail</label>
                        <input type="email" id="store-login-email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email" inputmode="email" placeholder="nome@exemplo.com" autofocus>
                    </div>
                    <div class="ui-form-field">
                        <label for="store-login-password">Senha</label>
                        <div class="ui-password">
                            <input type="password" id="store-login-password" name="password" required autocomplete="current-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block store-auth-submit">Entrar</button>
                </form>
                <p class="store-auth-switch">Ainda não tem conta? <a href="<?= htmlspecialchars($__entrarUrl . '?modo=cadastro') ?>">Criar conta</a></p>
            <?php else: ?>
                <form method="post" action="<?= base_url('criar-conta') ?>" class="store-auth-form" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="auth_intent" value="register">
                    <input type="hidden" name="origem" value="<?= htmlspecialchars($store['slug']) ?>">
                    <div class="ui-form-field">
                        <label for="store-register-name">Nome</label>
                        <input type="text" id="store-register-name" name="name" required value="<?= htmlspecialchars(old('name')) ?>" autocomplete="name" placeholder="Seu nome" autofocus>
                    </div>
                    <div class="ui-form-field">
                        <label for="store-register-email">E-mail</label>
                        <input type="email" id="store-register-email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email" inputmode="email" placeholder="nome@exemplo.com">
                    </div>
                    <div class="ui-form-field">
                        <label for="store-register-password">Senha</label>
                        <div class="ui-password">
                            <input type="password" id="store-register-password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="store-register-password-hint">
                        </div>
                        <p class="ui-form-hint" id="store-register-password-hint">Mínimo de 8 caracteres.</p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block store-auth-submit">Criar conta</button>
                    <p class="store-auth-terms">Ao criar a conta, você concorda com os <a href="<?= base_url('privacidade') ?>" target="_blank" rel="noopener">Termos de uso e a Política de privacidade</a>.</p>
                </form>
                <p class="store-auth-switch">Já tem conta? <a href="<?= htmlspecialchars($__entrarUrl) ?>">Entrar</a></p>
            <?php endif; ?>
        </section>

        <aside class="store-auth-perks" aria-label="Vantagens de ter conta">
            <h2 class="store-auth-perks-title">Com sua conta você pode</h2>
            <ul>
                <li><?= ui_icon('cart', 18) ?><span>Concluir o pedido e pagar com PIX ou cartão</span></li>
                <li><?= ui_icon('truck', 18) ?><span>Acompanhar a entrega de cada pedido</span></li>
                <li><?= ui_icon('heart', 18) ?><span>Salvar produtos nos favoritos</span></li>
                <li><?= ui_icon('pin', 18) ?><span>Guardar endereços para a próxima compra</span></li>
            </ul>
        </aside>
    </div>
</div>
<?php
$content = ob_get_clean();
unset($_SESSION['_old']);
require __DIR__ . '/layout_store.php';
