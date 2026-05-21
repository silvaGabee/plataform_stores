<div class="store-vitrine-empty">
    <div class="store-vitrine-empty-card">
        <div class="store-vitrine-empty-visual" aria-hidden="true">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M20 7H4M20 7L18 19H6L4 7M20 7L18.32 3.55C18.14 3.22 17.79 3 17.41 3H6.59C6.21 3 5.86 3.22 5.68 3.55L4 7" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 11V17M15 11V17" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
            </svg>
        </div>
        <h2 class="store-vitrine-empty-title"><?= htmlspecialchars($empty_title ?? 'Catálogo em preparação') ?></h2>
        <p class="store-vitrine-empty-desc"><?= htmlspecialchars($empty_desc ?? 'Ainda não há produtos publicados nesta vitrine. Volte em breve ou fale com a loja para mais informações.') ?></p>
        <?php if (!empty($empty_back_url) && !empty($empty_back_label)): ?>
            <p class="store-vitrine-empty-action">
                <a href="<?= htmlspecialchars($empty_back_url) ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars($empty_back_label) ?></a>
            </p>
        <?php endif; ?>
    </div>
</div>
