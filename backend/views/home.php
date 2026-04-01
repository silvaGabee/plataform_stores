<?php
$content = ob_start();
?>
<main class="page home-landing">
    <section class="home-hero">
        <div class="container home-hero-grid">
            <div class="home-hero-copy">
                <p class="home-eyebrow">Plataforma de Lojas</p>
                <h1 class="home-headline">A melhor experiência para vender <span class="home-headline-accent">online e no balcão</span></h1>
                <p class="home-lead">Crie a sua vitrine, organize produtos e stock, receba pedidos e acompanhe tudo num painel simples — ideal para equipas que querem crescer com profissionalismo.</p>
                <div class="home-hero-cta">
                    <button type="button" class="btn home-cta-primary js-auth-open" data-auth-tab="cadastro">Criar conta grátis</button>
                    <button type="button" class="btn home-cta-secondary js-auth-open" data-auth-tab="login">Já tenho conta</button>
                </div>
                <p class="home-microcopy">Comece em minutos. Entre, escolha ou crie a sua loja e comece a vender.</p>
            </div>
            <div class="home-hero-visual" aria-hidden="true">
                <div class="home-mockup home-mockup-store">
                    <div class="home-mockup-chrome">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="home-mockup-body">
                        <div class="home-mockup-nav"></div>
                        <div class="home-mockup-hero-mini"></div>
                        <div class="home-mockup-products">
                            <span></span><span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
                <div class="home-mockup home-mockup-panel">
                    <div class="home-mockup-chrome">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="home-mockup-panel-rows">
                        <span class="home-mockup-bar"></span>
                        <span class="home-mockup-bar short"></span>
                        <span class="home-mockup-bar"></span>
                        <span class="home-mockup-bar tiny"></span>
                    </div>
                </div>
                <div class="home-float-card">
                    <strong>Pedido #1284</strong>
                    <span class="home-float-pill">A caminho</span>
                </div>
            </div>
        </div>
    </section>

    <section class="home-features">
        <div class="container">
            <h2 class="home-section-title">O que você ganha com a plataforma</h2>
            <p class="home-section-sub">Um ecossistema pensado para lojas físicas e digitais trabalharem juntas.</p>
            <ul class="home-feature-grid">
                <li class="home-feature-card">
                    <span class="home-feature-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <h3>Loja pública</h3>
                    <p>Vitrine com produtos, carrinho e checkout para os seus clientes comprarem com confiança.</p>
                </li>
                <li class="home-feature-card">
                    <span class="home-feature-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="12"/></svg>
                    </span>
                    <h3>Painel da loja</h3>
                    <p>Stock, entregas, PDV, relatórios e configurações num só sítio, acessível pela sua equipa.</p>
                </li>
                <li class="home-feature-card">
                    <span class="home-feature-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <h3>Conta e permissões</h3>
                    <p>Uma conta para gerir várias lojas e convidar quem precisa, com o nível de acesso certo.</p>
                </li>
            </ul>
        </div>
    </section>

    <section class="home-bottom-cta">
        <div class="container home-bottom-inner">
            <div>
                <h2 class="home-bottom-title">Pronto para começar?</h2>
                <p class="home-bottom-text">Faça login ou crie a sua conta e acesse as lojas às quais pertence.</p>
            </div>
            <div class="home-bottom-actions">
                <a href="<?= base_url('lojas') ?>" class="btn home-bottom-link">Ver minhas lojas</a>
                <button type="button" class="btn btn-primary js-auth-open" data-auth-tab="cadastro">Criar conta</button>
            </div>
        </div>
    </section>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
