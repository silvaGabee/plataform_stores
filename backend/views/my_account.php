<?php
ob_start();
$user = $user ?? [];
$name = htmlspecialchars($user['name'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$typeRaw = $user['user_type'] ?? '';
$typeLabels = [
    'cliente'     => 'Cliente',
    'gerente'     => 'Gerente',
    'funcionario' => 'Funcionário',
];
$typeLabel = $typeLabels[$typeRaw] ?? htmlspecialchars((string) $typeRaw);

$initial = '?';
$n = trim((string) ($user['name'] ?? ''));
if ($n !== '') {
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($n, 0, 1))
        : strtoupper(substr($n, 0, 1));
}
$initialEsc = htmlspecialchars($initial);
?>
<main class="page my-account my-account-page">
    <header class="my-account-hero">
        <div class="my-account-hero-glow" aria-hidden="true"></div>
        <div class="container my-account-hero-inner">
            <div class="my-account-hero-copy">
                <p class="my-account-eyebrow">Conta</p>
                <h1 class="my-account-title">Minha <span class="my-account-title-accent">conta</span></h1>
                <p class="my-account-lead">Dados do seu utilizador na plataforma. Mantenha o nome e o e-mail atualizados, especialmente se for criar lojas.</p>
            </div>
        </div>
    </header>

    <div class="container my-account-body">
        <?php if (!empty($_SESSION['_error'])): ?>
            <div class="alert alert-error my-account-alert" role="alert">
                <?= htmlspecialchars($_SESSION['_error']) ?>
            </div>
            <?php unset($_SESSION['_error']); ?>
        <?php endif; ?>

        <div class="my-account-shell">
            <section class="my-account-profile-card" aria-labelledby="my-account-profile-heading">
                <h2 id="my-account-profile-heading" class="visually-hidden">Dados do perfil</h2>
                <div class="my-account-profile-head">
                    <div class="my-account-avatar" aria-hidden="true"><?= $initialEsc ?></div>
                    <div class="my-account-profile-intro">
                        <p class="my-account-profile-name"><?= $name !== '' ? $name : '—' ?></p>
                        <span class="my-account-badge my-account-badge--<?= htmlspecialchars(preg_replace('/[^a-z]/', '', (string) $typeRaw) ?: 'cliente') ?>"><?= $typeLabel !== '' ? $typeLabel : '—' ?></span>
                    </div>
                </div>

                <ul class="my-account-details">
                    <li class="my-account-detail-row">
                        <span class="my-account-detail-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <div class="my-account-detail-text">
                            <span class="my-account-detail-label">E-mail</span>
                            <span class="my-account-detail-value"><?= $email !== '' ? $email : '—' ?></span>
                        </div>
                    </li>
                    <li class="my-account-detail-row">
                        <span class="my-account-detail-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <div class="my-account-detail-text">
                            <span class="my-account-detail-label">Perfil</span>
                            <span class="my-account-detail-value"><?= $typeLabel !== '' ? $typeLabel : '—' ?></span>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="my-account-footer-actions">
                <a href="<?= base_url('lojas') ?>" class="my-account-btn-back">Voltar às lojas</a>
                <div class="my-account-danger-card">
                    <p class="my-account-danger-title">Zona de risco</p>
                    <p class="my-account-danger-text">A exclusão da conta é permanente e só é permitida se não houver restrições no sistema.</p>
                    <form method="post" action="<?= base_url('minha-conta/excluir') ?>" class="my-account-delete-form">
                        <button type="submit" class="btn btn-danger my-account-btn-delete" onclick="return confirm('Tem a certeza de que deseja excluir a sua conta? Esta ação não pode ser desfeita.');">
                            Excluir a minha conta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
