<?php
$content = ob_start();
?>
<main class="page stores">
    <div class="container">
        <h1>Lojas existentes</h1>
        <?php
        $myStores = $my_stores ?? [];
        $availableStores = $available_stores ?? [];
        $hasAny = !empty($myStores) || !empty($availableStores);
        ?>
        <?php if (!$hasAny): ?>
            <p>Nenhuma loja cadastrada ainda.</p>
        <?php else: ?>
            <?php
            $renderStoreCard = function (array $s): void {
                $href = htmlspecialchars(base_url('loja/' . $s['slug']));
                $nameEsc = htmlspecialchars($s['name']);
                ?>
                <li class="store-card card">
                    <a href="<?= $href ?>" class="store-card-name"><?= $nameEsc ?></a>
                    <?php if (!empty($s['city'])): ?>
                        <p class="store-card-city"><?= htmlspecialchars($s['city']) ?></p>
                    <?php endif; ?>
                </li>
                <?php
            };
            ?>
            <?php if (!empty($myStores)): ?>
                <section class="stores-section stores-my-work" aria-labelledby="stores-my-work-heading">
                    <h2 id="stores-my-work-heading" class="stores-section-title">Lojas que trabalho</h2>
                    <ul class="store-cards">
                        <?php foreach ($myStores as $s): ?>
                            <?php $renderStoreCard($s); ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="stores-section stores-available" aria-labelledby="stores-available-heading">
                <h2 id="stores-available-heading" class="stores-section-title">Lojas disponíveis</h2>
                <?php if (empty($availableStores)): ?>
                    <p class="stores-section-empty">Nenhuma outra loja no momento.</p>
                <?php else: ?>
                    <ul class="store-cards">
                        <?php foreach ($availableStores as $s): ?>
                            <?php $renderStoreCard($s); ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <p class="store-list-actions">
            <a href="<?= base_url('criar-loja') ?>" class="btn btn-primary">Criar minha loja</a>
        </p>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
