<?php
$content = ob_start();
?>
<main class="page home">
    <div class="container">
        <h1>Plataforma de Lojas</h1>
        <p class="subtitle">Crie sua loja e comece a vender online e no balcão.</p>
        <p>
            <a href="<?= base_url('lojas') ?>" class="btn btn-secondary">Lojas existentes</a>
        </p>
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error">
                <strong>Erro:</strong> <?= htmlspecialchars($_SESSION['_error']) ?>
                <br><small>Se for erro de banco, <a href="<?= base_url('test-conexao.php') ?>">teste a conexão aqui</a> e confira se executou o backend/database/schema.sql.</small>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>
        <form method="post" action="" class="card form-create-store">
            <h2>Criar minha loja</h2>
            <label>Nome da loja *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars(old('name')) ?>">
            <label>Seu nome (gerente) *</label>
            <input type="text" name="manager_name" required value="<?= htmlspecialchars(old('manager_name')) ?>">
            <label>E-mail *</label>
            <input type="email" name="manager_email" required value="<?= htmlspecialchars(old('manager_email')) ?>">
            <label>Senha *</label>
            <input type="password" name="manager_password" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Criar loja</button>
            </div>
        </form>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
