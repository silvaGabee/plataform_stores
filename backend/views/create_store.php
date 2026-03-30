<?php
$content = ob_start();
?>
<main class="page create-store">
    <div class="container">
        <h1>Criar minha loja</h1>
        <p><a href="<?= base_url('lojas') ?>" class="btn btn-secondary">← Voltar às lojas</a></p>
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error">
                <strong>Erro:</strong> <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>
        <form method="post" action="<?= base_url('criar-loja') ?>" class="card form-create-store">
            <h2>Nova loja</h2>
            <label>Nome da loja *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars(old('name')) ?>" placeholder="Ex: Minha Loja">
            <label>Categoria</label>
            <input type="text" name="category" value="<?= htmlspecialchars(old('category')) ?>" placeholder="Ex: Roupas, Alimentos">
            <label>Cidade</label>
            <input type="text" name="city" value="<?= htmlspecialchars(old('city')) ?>" placeholder="Ex: São Paulo">
            <label>Telefone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars(old('phone')) ?>" placeholder="Ex: (11) 99999-9999">
            <hr style="margin:1rem 0">
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
