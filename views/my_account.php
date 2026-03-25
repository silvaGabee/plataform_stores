<?php
$content = ob_start();
$user = $user ?? [];
$name = htmlspecialchars($user['name'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$typeRaw = $user['user_type'] ?? '';
$typeLabels = [
    'cliente'     => 'Cliente',
    'gerente'     => 'Gerente',
    'funcionario' => 'Funcionário',
];
$typeLabel = $typeLabels[$typeRaw] ?? htmlspecialchars($typeRaw);
?>
<main class="page my-account">
    <div class="container">
        <h1>Minha conta</h1>
        <p class="subtitle">Dados do seu utilizador na plataforma.</p>
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>
        <div class="card">
            <dl class="my-account-dl">
                <dt>Nome</dt>
                <dd><?= $name !== '' ? $name : '—' ?></dd>
                <dt>E-mail</dt>
                <dd><?= $email !== '' ? $email : '—' ?></dd>
                <dt>Perfil</dt>
                <dd><?= $typeLabel !== '' ? $typeLabel : '—' ?></dd>
            </dl>
        </div>
        <div class="my-account-actions">
            <a href="<?= base_url('lojas') ?>" class="btn btn-secondary">← Voltar às lojas</a>
            <form method="post" action="<?= base_url('minha-conta/excluir') ?>" class="my-account-delete-form">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem a certeza de que deseja excluir a sua conta? Esta ação não pode ser desfeita.');">
                    Excluir conta
                </button>
            </form>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
