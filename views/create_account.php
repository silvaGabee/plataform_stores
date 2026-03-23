<?php
$content = ob_start();
?>
<main class="page create-account">
    <div class="container">
        <h1>Criar minha conta</h1>
        <p><a href="<?= base_url() ?>" class="btn btn-secondary">← Voltar ao login</a></p>
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>
        <form method="post" action="<?= base_url('criar-conta') ?>" class="card form-create-account">
            <h2>Cadastro</h2>
            <label>Nome *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars(old('name')) ?>" autocomplete="name">
            <label>E-mail *</label>
            <input type="email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email">
            <label>Senha *</label>
            <input type="password" name="password" required autocomplete="new-password">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Criar conta</button>
            </div>
        </form>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
