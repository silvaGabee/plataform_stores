<?php
$authQuery = $_GET['auth'] ?? '';
$authModalInitial = ($authQuery === 'login' || $authQuery === 'cadastro') ? $authQuery : '';
$hide_app_header = isset($hide_app_header) && $hide_app_header;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Plataforma de Lojas') ?></title>
    <link rel="icon" href="<?= htmlspecialchars(favicon_url(), ENT_QUOTES, 'UTF-8') ?>" sizes="any">
    <link rel="shortcut icon" href="<?= htmlspecialchars(favicon_url(), ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
    <?php if (!logged_in()): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&display=swap" rel="stylesheet">
    <?php endif; ?>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="<?= !logged_in() ? 'has-public-header' : '' ?><?= $hide_app_header ? ' hide-app-header' : '' ?>" data-auth-modal-initial="<?= htmlspecialchars($authModalInitial) ?>">
    <?php if (!$hide_app_header): ?>
    <header class="app-header<?= logged_in() ? ' app-header--session' : '' ?>">
        <div class="container app-header-inner">
            <a href="<?= base_url(logged_in() ? 'lojas' : '') ?>" class="app-header-brand">
                <img src="<?= favicon_url() ?>" alt="" class="app-header-brand-mark" decoding="async">
                <span class="app-header-brand-text">Plataforma de Lojas</span>
            </a>
            <?php if (logged_in()): ?>
                <nav class="app-header-actions app-header-actions-session" aria-label="Conta">
                    <a href="<?= base_url('minha-conta') ?>" class="app-header-minha-conta">
                        <svg class="app-header-minha-conta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Minha conta</span>
                    </a>
                    <a href="<?= base_url('sair') ?>" class="app-header-logout">Sair</a>
                </nav>
            <?php else: ?>
                <nav class="app-header-actions app-header-actions-guest" aria-label="Acesso">
                    <button type="button" class="btn-header-ghost js-auth-open" data-auth-tab="login">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Entrar
                    </button>
                    <button type="button" class="btn-header-primary js-auth-open" data-auth-tab="cadastro">Criar conta</button>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>
    <?= $content ?? '' ?>

    <?php if (!logged_in()): ?>
    <div id="auth-modal" class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title" hidden>
        <div class="auth-modal-backdrop js-auth-close" aria-hidden="true"></div>
        <div class="auth-modal-panel">
            <button type="button" class="auth-modal-close js-auth-close" aria-label="Fechar">&times;</button>
            <div class="auth-modal-layout">
                <aside class="auth-modal-visual" aria-hidden="true">
                    <p class="auth-modal-visual-eyebrow">Tudo num só lugar</p>
                    <h2 id="auth-modal-title" class="auth-modal-visual-title">Venda online e no balcão com confiança</h2>
                    <p class="auth-modal-visual-text">Catálogo, carrinho, checkout e painel para a sua equipe — pensado para quem quer crescer sem complicar.</p>
                    <ul class="auth-modal-visual-list">
                        <li>Loja pública com vitrine e produtos</li>
                        <li>Painel para stock, entregas e relatórios</li>
                        <li>Conta única para gerir as suas lojas</li>
                    </ul>
                </aside>
                <div class="auth-modal-forms">
                    <div class="auth-modal-tabs" role="tablist">
                        <button type="button" class="auth-modal-tab<?= $authModalInitial === 'cadastro' ? '' : ' is-active' ?>" role="tab" id="tab-login" aria-selected="<?= $authModalInitial === 'cadastro' ? 'false' : 'true' ?>" aria-controls="panel-login" data-auth-tab="login">Entrar</button>
                        <button type="button" class="auth-modal-tab<?= $authModalInitial === 'cadastro' ? ' is-active' : '' ?>" role="tab" id="tab-cadastro" aria-selected="<?= $authModalInitial === 'cadastro' ? 'true' : 'false' ?>" aria-controls="panel-cadastro" data-auth-tab="cadastro">Criar conta</button>
                    </div>
                    <div id="panel-login" class="auth-modal-pane<?= $authModalInitial === 'cadastro' ? '' : ' is-active' ?>" role="tabpanel" aria-labelledby="tab-login"<?= $authModalInitial === 'cadastro' ? ' hidden' : '' ?>>
                        <?php
                        $loginError = !empty($_SESSION['_error']) && $authModalInitial === 'login';
                        $loginSuccess = !empty($_SESSION['_success']) && $authModalInitial === 'login';
                        ?>
                        <?php if ($loginError): ?>
                            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['_error']) ?></div>
                            <?php unset($_SESSION['_error']); ?>
                        <?php endif; ?>
                        <?php if ($loginSuccess): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['_success']) ?></div>
                            <?php unset($_SESSION['_success']); ?>
                        <?php endif; ?>
                        <form method="post" action="<?= base_url('login') ?>" class="form-login auth-modal-form">
                            <input type="hidden" name="auth_intent" value="login">
                            <label for="modal-login-email">E-mail</label>
                            <input type="email" id="modal-login-email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email" placeholder="nome@exemplo.com">
                            <label for="modal-login-password">Senha</label>
                            <input type="password" id="modal-login-password" name="password" required autocomplete="current-password" placeholder="••••••••">
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                            </div>
                        </form>
                    </div>
                    <div id="panel-cadastro" class="auth-modal-pane<?= $authModalInitial === 'cadastro' ? ' is-active' : '' ?>" role="tabpanel" aria-labelledby="tab-cadastro"<?= $authModalInitial === 'cadastro' ? '' : ' hidden' ?>>
                        <?php if (!empty($_SESSION['_error']) && $authModalInitial === 'cadastro'): ?>
                            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['_error']) ?></div>
                            <?php unset($_SESSION['_error']); ?>
                        <?php endif; ?>
                        <form method="post" action="<?= base_url('criar-conta') ?>" class="form-create-account auth-modal-form">
                            <input type="hidden" name="auth_intent" value="register">
                            <label for="modal-register-name">Nome</label>
                            <input type="text" id="modal-register-name" name="name" required value="<?= htmlspecialchars(old('name')) ?>" autocomplete="name" placeholder="O seu nome">
                            <label for="modal-register-email">E-mail</label>
                            <input type="email" id="modal-register-email" name="email" required value="<?= htmlspecialchars(old('email')) ?>" autocomplete="email" placeholder="nome@exemplo.com">
                            <label for="modal-register-password">Senha</label>
                            <input type="password" id="modal-register-password" name="password" required autocomplete="new-password" placeholder="Mínimo 6 caracteres">
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-block">Criar conta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>
