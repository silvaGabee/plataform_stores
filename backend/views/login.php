<?php
ob_start();
?>
<main class="page login">
    <div class="container">
        <h1>Plataforma de Lojas</h1>
        <p class="subtitle">Entre com seu e-mail e senha para acessar.</p>
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['_success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['_success']) ?>
            </div>
            <?php unset($_SESSION['_success']); ?>
        <?php endif; ?>
        <form method="post" action="<?= base_url('login') ?>" class="card form-login">
            <input type="hidden" name="auth_intent" value="login">
            <h2>Entrar</h2>
            <label for="login-email">E-mail *</label>
            <input type="email" id="login-email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email">
            <label for="login-password">Senha *</label>
            <input type="password" id="login-password" name="password" required autocomplete="current-password">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Entrar</button>
            </div>
        </form>
        <p class="login-footer">
            Ainda não tem uma conta? <a href="<?= base_url('criar-conta') ?>">Criar a minha conta</a>
        </p>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
