<?php
ob_start();

$myStores = $my_stores ?? [];
$availableStores = $available_stores ?? [];
$hasAny = !empty($myStores) || !empty($availableStores);

$storeHue = static function (string $slug): int {
    return abs(crc32($slug)) % 360;
};

$storeInitial = static function (string $name): string {
    $n = trim($name);
    if ($n === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($n, 0, 1));
    }
    return strtoupper(substr($n, 0, 1));
};

$renderStoreCard = function (array $s, bool $isMine) use ($storeHue, $storeInitial): void {
    $slug = $s['slug'];
    $hrefVitrine = htmlspecialchars(base_url('loja/' . $slug));
    $hrefPainel = htmlspecialchars(base_url('painel/' . $slug));
    $nameEsc = htmlspecialchars($s['name']);
    $hue = $storeHue((string) $slug);
    $initial = htmlspecialchars($storeInitial((string) $s['name']));
    $city = !empty($s['city']) ? htmlspecialchars((string) $s['city']) : '';
    $category = !empty($s['category']) ? htmlspecialchars((string) $s['category']) : '';
    $phone = !empty($s['phone']) ? htmlspecialchars((string) $s['phone']) : '';
    ?>
    <li class="store-card-pro">
        <div class="store-card-pro-inner">
            <div class="store-card-pro-top">
                <div class="store-card-pro-avatar" style="--store-hue: <?= (int) $hue ?>;" aria-hidden="true"><?= $initial ?></div>
                <div class="store-card-pro-heading">
                    <h3 class="store-card-pro-title">
                        <a href="<?= $hrefVitrine ?>"><?= $nameEsc ?></a>
                    </h3>
                    <?php if ($isMine): ?>
                        <span class="store-card-pro-badge">A sua equipa</span>
                    <?php else: ?>
                        <span class="store-card-pro-badge store-card-pro-badge-muted">Vitrine pública</span>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="store-card-pro-meta">
                <?php if ($city !== ''): ?>
                    <li>
                        <span class="store-card-pro-meta-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <?= $city ?>
                    </li>
                <?php endif; ?>
                <?php if ($category !== ''): ?>
                    <li>
                        <span class="store-card-pro-meta-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        </span>
                        <?= $category ?>
                    </li>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <li>
                        <span class="store-card-pro-meta-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </span>
                        <?= $phone ?>
                    </li>
                <?php endif; ?>
                <?php if ($city === '' && $category === '' && $phone === ''): ?>
                    <li class="store-card-pro-meta-empty">Sem localização ou contacto registados</li>
                <?php endif; ?>
            </ul>
            <div class="store-card-pro-actions">
                <a href="<?= $hrefVitrine ?>" class="store-card-pro-btn store-card-pro-btn-primary">Ver vitrine</a>
                <?php if ($isMine): ?>
                    <a href="<?= $hrefPainel ?>" class="store-card-pro-btn store-card-pro-btn-secondary">Abrir painel</a>
                <?php endif; ?>
            </div>
        </div>
    </li>
    <?php
};
?>
<main class="page stores stores-page">
    <header class="stores-page-hero">
        <div class="stores-page-hero-glow" aria-hidden="true"></div>
        <div class="container stores-hero-inner">
            <div class="stores-hero-copy">
                <p class="stores-hero-eyebrow">Painel</p>
                <h1 class="stores-page-title">As suas <span class="stores-page-title-accent">lojas</span></h1>
                <p class="stores-page-lead">Vitrine pública para clientes e painel para a equipe — tudo acessível a partir daqui.</p>
            </div>
            <a href="<?= base_url('criar-loja') ?>" class="stores-hero-cta">Criar nova loja</a>
        </div>
    </header>

    <div class="container stores-page-body">
        <?php if (!$hasAny): ?>
            <div class="stores-empty-state">
                <div class="stores-empty-icon" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h2 class="stores-empty-title">Ainda não há lojas</h2>
                <p class="stores-empty-text">Seja o primeiro a criar uma loja na plataforma e comece a vender online.</p>
                <a href="<?= base_url('criar-loja') ?>" class="btn btn-primary stores-empty-cta">Criar a minha loja</a>
            </div>
        <?php else: ?>
            <?php if (!empty($myStores)): ?>
                <section class="stores-block" aria-labelledby="stores-my-work-heading">
                    <div class="stores-block-head">
                        <h2 id="stores-my-work-heading" class="stores-block-title">Lojas em que trabalho</h2>
                        <span class="stores-block-count"><?= count($myStores) ?></span>
                    </div>
                    <p class="stores-block-desc">Acesso à vitrine e ao painel de gestão.</p>
                    <ul class="store-cards-pro">
                        <?php foreach ($myStores as $s): ?>
                            <?php $renderStoreCard($s, true); ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="stores-block stores-block-available" aria-labelledby="stores-available-heading">
                <div class="stores-block-head">
                    <h2 id="stores-available-heading" class="stores-block-title">Outras lojas</h2>
                    <?php if (!empty($availableStores)): ?>
                        <span class="stores-block-count"><?= count($availableStores) ?></span>
                    <?php endif; ?>
                </div>
                <p class="stores-block-desc">Vitrines públicas na plataforma (apenas visualização).</p>
                <?php if (empty($availableStores)): ?>
                    <p class="stores-section-empty-pro">Não há outras lojas listadas neste momento.</p>
                <?php else: ?>
                    <ul class="store-cards-pro">
                        <?php foreach ($availableStores as $s): ?>
                            <?php $renderStoreCard($s, false); ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
