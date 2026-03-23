<?php $content = ob_start(); ?>
<div class="container">
    <h1>Meus endereços</h1>
    <?php if (!empty($logged_in_used)): ?>
        <p class="text-muted">Endereços cadastrados na sua conta (<?= htmlspecialchars($email ?? '') ?>).</p>
    <?php else: ?>
        <p class="text-muted">Informe o e-mail usado nas compras para ver os endereços. Ou <a href="<?= base_url() ?>">faça login</a> para ver pela sua conta.</p>
        <form method="get" action="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>" class="card" style="max-width: 400px; margin-bottom: 1.5rem;">
            <label for="meus-enderecos-email">E-mail</label>
            <input type="email" id="meus-enderecos-email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="seu@email.com" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($email_searched ?? false): ?>
        <?php if (empty($addresses)): ?>
            <p class="text-muted">Nenhum endereço cadastrado para este e-mail. Você pode cadastrar um no checkout ao escolher entrega.</p>
        <?php else: ?>
            <ul class="meus-enderecos-list">
                <?php foreach ($addresses as $a): ?>
                    <li class="meus-enderecos-item card" style="max-width: 480px;">
                        <?php if (!empty($a['label'])): ?><strong><?= htmlspecialchars($a['label']) ?></strong><br><?php endif; ?>
                        <?= htmlspecialchars($a['street']) ?>, <?= htmlspecialchars($a['number']) ?>
                        <?php if (!empty($a['complement'])): ?> — <?= htmlspecialchars($a['complement']) ?><?php endif; ?>
                        <br>
                        <?php if (!empty($a['neighborhood'])): ?><?= htmlspecialchars($a['neighborhood']) ?> — <?php endif; ?>
                        <?= htmlspecialchars($a['city']) ?>/<?= htmlspecialchars($a['state']) ?> — CEP <?= htmlspecialchars($a['zipcode']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
    <p style="margin-top: 1.5rem;"><a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-secondary">← Voltar à loja</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_store.php';
