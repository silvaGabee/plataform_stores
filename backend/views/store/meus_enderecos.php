<?php
$content = ob_start();
$can_add_address = !empty($email_searched) && ($email ?? '') !== '';
?>
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
            <p class="text-muted">Nenhum endereço cadastrado para este e-mail. Use o formulário abaixo para adicionar um endereço à sua conta.</p>
        <?php else: ?>
            <ul class="meus-enderecos-list">
                <?php foreach ($addresses as $a): ?>
                    <?php
                    $addrJson = htmlspecialchars(json_encode([
                        'id' => (int) $a['id'],
                        'label' => (string) ($a['label'] ?? ''),
                        'street' => (string) ($a['street'] ?? ''),
                        'number' => (string) ($a['number'] ?? ''),
                        'complement' => (string) ($a['complement'] ?? ''),
                        'neighborhood' => (string) ($a['neighborhood'] ?? ''),
                        'city' => (string) ($a['city'] ?? ''),
                        'state' => (string) ($a['state'] ?? ''),
                        'zipcode' => (string) ($a['zipcode'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li class="meus-enderecos-item card" style="max-width: 480px;">
                        <?php if (!empty($a['label'])): ?><strong><?= htmlspecialchars($a['label']) ?></strong><br><?php endif; ?>
                        <?= htmlspecialchars($a['street']) ?>, <?= htmlspecialchars($a['number']) ?>
                        <?php if (!empty($a['complement'])): ?> — <?= htmlspecialchars($a['complement']) ?><?php endif; ?>
                        <br>
                        <?php if (!empty($a['neighborhood'])): ?><?= htmlspecialchars($a['neighborhood']) ?> — <?php endif; ?>
                        <?= htmlspecialchars($a['city']) ?>/<?= htmlspecialchars($a['state']) ?> — CEP <?= htmlspecialchars($a['zipcode']) ?>
                        <div class="meus-enderecos-item-actions">
                            <button type="button" class="btn btn-sm btn-secondary meus-enderecos-edit" data-address="<?= $addrJson ?>">Editar</button>
                            <button type="button" class="btn btn-sm meus-enderecos-btn-delete meus-enderecos-delete" data-address-id="<?= (int) $a['id'] ?>">Excluir</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-bottom: 0.75rem;">
                <button type="button" id="meus-enderecos-toggle" class="btn btn-secondary">+ Adicionar outro endereço</button>
            </p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($can_add_address): ?>
        <div
            id="meus-enderecos-page"
            class="card meus-enderecos-add-panel<?= !empty($addresses) ? ' hidden' : '' ?>"
            style="max-width: 520px; margin-bottom: 1rem;"
            data-store-slug="<?= htmlspecialchars($store['slug']) ?>"
        >
            <h2 id="meus-enderecos-form-heading" class="meus-enderecos-add-title" style="margin-top: 0; font-size: 1.1rem;">Adicionar endereço</h2>
            <input type="hidden" id="meus-addr-id" value="">
            <input type="hidden" id="meus-addr-email" value="<?= htmlspecialchars($email) ?>">
            <?php if (($customer_name ?? '') !== ''): ?>
                <input type="hidden" id="meus-addr-customer-name" value="<?= htmlspecialchars($customer_name) ?>">
            <?php else: ?>
                <div class="checkout-field">
                    <label for="meus-addr-customer-name">Seu nome *</label>
                    <input type="text" id="meus-addr-customer-name" required placeholder="Nome completo" autocomplete="name">
                </div>
            <?php endif; ?>
            <div class="checkout-field">
                <label for="meus-addr-label">Apelido (opcional)</label>
                <input type="text" id="meus-addr-label" placeholder="Ex.: Casa, Trabalho" maxlength="120">
            </div>
            <div class="checkout-field">
                <label for="meus-addr-street">Rua *</label>
                <input type="text" id="meus-addr-street" required placeholder="Rua, avenida" autocomplete="street-address">
            </div>
            <div class="checkout-address-row">
                <div class="checkout-field">
                    <label for="meus-addr-number">Número *</label>
                    <input type="text" id="meus-addr-number" required placeholder="Nº">
                </div>
                <div class="checkout-field">
                    <label for="meus-addr-complement">Complemento</label>
                    <input type="text" id="meus-addr-complement" placeholder="Apto, bloco">
                </div>
            </div>
            <div class="checkout-field">
                <label for="meus-addr-neighborhood">Bairro</label>
                <input type="text" id="meus-addr-neighborhood" placeholder="Bairro">
            </div>
            <div class="checkout-address-row">
                <div class="checkout-field">
                    <label for="meus-addr-city">Cidade *</label>
                    <input type="text" id="meus-addr-city" required placeholder="Cidade" autocomplete="address-level2">
                </div>
                <div class="checkout-field">
                    <label for="meus-addr-state">UF *</label>
                    <input type="text" id="meus-addr-state" required placeholder="SC" maxlength="2" autocomplete="address-level1">
                </div>
                <div class="checkout-field">
                    <label for="meus-addr-zipcode">CEP *</label>
                    <input type="text" id="meus-addr-zipcode" required placeholder="00000-000" autocomplete="postal-code">
                </div>
            </div>
            <p id="meus-enderecos-form-msg" class="text-muted" role="status" style="min-height: 1.25rem;"></p>
            <div class="form-actions meus-enderecos-form-actions" style="margin-top: 0; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <button type="button" id="meus-enderecos-submit" class="btn btn-primary">Salvar endereço</button>
                <button type="button" id="meus-enderecos-cancel-edit" class="btn btn-outline btn-sm hidden">Cancelar edição</button>
            </div>
        </div>
    <?php endif; ?>
    <p class="meus-enderecos-footer-actions" style="margin-top: 1.5rem;">
        <a href="<?= base_url("loja/{$store['slug']}") ?>" class="btn btn-secondary">← Voltar à loja</a>
    </p>
</div>
<?php
$content = ob_get_clean();
$extra_js = '';
if ($can_add_address) {
    $baseUrl = rtrim(base_url(), '/');
    $extra_js = '<script>window.BASE_URL = ' . json_encode($baseUrl) . ';</script>'
        . '<script src="' . asset('js/meus-enderecos.js') . '"></script>';
}
require __DIR__ . '/layout_store.php';
