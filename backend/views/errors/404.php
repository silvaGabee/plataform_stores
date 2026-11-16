<?php
/** @var array{titulo: string, mensagem: string, acao_url: string, acao_texto: string} $not_found */
ob_start();
?>
<main class="page not-found-page">
    <div class="container not-found-inner">
        <p class="not-found-code" aria-hidden="true">404</p>
        <h1 class="not-found-title"><?= htmlspecialchars($not_found['titulo']) ?></h1>
        <p class="not-found-text"><?= htmlspecialchars($not_found['mensagem']) ?></p>
        <a href="<?= htmlspecialchars($not_found['acao_url']) ?>" class="btn btn-primary not-found-action"><?= htmlspecialchars($not_found['acao_texto']) ?></a>
    </div>
</main>
<?php
$content = ob_get_clean();
$meta_robots = 'noindex';
require __DIR__ . '/../layout.php';
