<?php
ob_start();
// A página só é servida para quem está logado (StoreFrontController::meusEnderecos),
// então cadastrar endereço está sempre disponível aqui.
$can_add_address = true;
?>
<div class="store-subpage store-meus-enderecos-page">
    <div class="store-subpage-inner container">
        <header class="store-subpage-head store-meus-enderecos-head">
            <div class="store-subpage-head-actions">
                <a href="<?= base_url("loja/{$store['slug']}") ?>" class="store-subpage-back-pill">Voltar à loja</a>
            </div>
            <h1 id="store-meus-enderecos-title" class="store-subpage-title">Meus endereços</h1>
            <p class="store-subpage-lead">Endereços cadastrados na sua conta (<strong><?= htmlspecialchars($email ?? '') ?></strong>).</p>
        </header>

        <div class="store-subpage-body">
            <?php // O formulário de busca por e-mail saiu: a lista é sempre da conta logada. ?>
                <?php if (empty($addresses)): ?>
                    <div class="store-empty-state store-empty-state--compact store-meus-enderecos-empty" role="status">
                        <div class="store-empty-state-icon" aria-hidden="true">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h2 class="store-empty-state-title">Nenhum endereço cadastrado</h2>
                        <p class="store-empty-state-text">Use o formulário abaixo para cadastrar um endereço nesta loja. Ele ficará disponível nas próximas compras.</p>
                    </div>
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
                            <li class="meus-enderecos-item meus-enderecos-address-card">
                                <div class="meus-enderecos-card-inner">
                                    <div class="meus-enderecos-card-icon-wrap" aria-hidden="true">
                                        <svg class="meus-enderecos-card-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="meus-enderecos-card-content">
                                        <?php if (!empty($a['label'])): ?>
                                            <p class="meus-enderecos-card-label"><?= htmlspecialchars($a['label']) ?></p>
                                        <?php endif; ?>
                                        <p class="meus-enderecos-card-lines">
                                            <span class="meus-enderecos-card-line-primary"><?= htmlspecialchars($a['street']) ?>, <?= htmlspecialchars($a['number']) ?><?php if (!empty($a['complement'])): ?> <span class="meus-enderecos-card-sep">·</span> <?= htmlspecialchars($a['complement']) ?><?php endif; ?></span>
                                            <span class="meus-enderecos-card-line-secondary"><?php if (!empty($a['neighborhood'])): ?><?= htmlspecialchars($a['neighborhood']) ?> <span class="meus-enderecos-card-sep">·</span> <?php endif; ?><?= htmlspecialchars($a['city']) ?>/<?= htmlspecialchars($a['state']) ?> <span class="meus-enderecos-card-sep">·</span> CEP <?= htmlspecialchars($a['zipcode']) ?></span>
                                        </p>
                                        <div class="meus-enderecos-item-actions">
                                            <button type="button" class="btn btn-secondary meus-enderecos-edit" data-address="<?= $addrJson ?>">Editar</button>
                                            <button type="button" class="btn btn-danger meus-enderecos-delete" data-address-id="<?= (int) $a['id'] ?>">Excluir</button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="meus-enderecos-add-more-wrap">
                        <button type="button" id="meus-enderecos-toggle" class="btn btn-secondary meus-enderecos-toggle-btn">+ Adicionar outro endereço</button>
                    </div>
                <?php endif; ?>

            <?php if ($can_add_address): ?>
                <div
                    id="meus-enderecos-page"
                    class="store-subpage-card meus-enderecos-add-panel<?= !empty($addresses) ? ' hidden' : '' ?>"
                    data-store-slug="<?= htmlspecialchars($store['slug']) ?>"
                >
                    <h2 id="meus-enderecos-form-heading" class="store-subpage-card-title meus-enderecos-add-title">Adicionar endereço</h2>
                    <div class="meus-enderecos-add-panel-fields">
                        <input type="hidden" id="meus-addr-id" value="">
                        <?php // O campo oculto de e-mail saiu: a API identifica o cliente pela sessão. ?>
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
                            <label for="meus-addr-zipcode">CEP *</label>
                            <input type="text" id="meus-addr-zipcode" required placeholder="00000-000" inputmode="numeric" autocomplete="postal-code" maxlength="9">
                            <p id="meus-addr-cep-status" class="cep-lookup-status" role="status" aria-live="polite"></p>
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
                        <div class="checkout-address-row checkout-address-row--double">
                            <div class="checkout-field">
                                <label for="meus-addr-city">Cidade *</label>
                                <input type="text" id="meus-addr-city" required placeholder="Cidade" autocomplete="address-level2">
                            </div>
                            <div class="checkout-field">
                                <label for="meus-addr-state">UF *</label>
                                <input type="text" id="meus-addr-state" required placeholder="SC" maxlength="2" autocomplete="address-level1" style="text-transform: uppercase">
                            </div>
                        </div>
                        <p id="meus-enderecos-form-msg" class="meus-enderecos-form-msg text-muted" role="status"></p>
                        <div class="form-actions meus-enderecos-form-actions">
                            <button type="button" id="meus-enderecos-submit" class="btn btn-primary">Salvar endereço</button>
                            <button type="button" id="meus-enderecos-cancel-edit" class="btn btn-outline btn-sm hidden">Cancelar edição</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$extra_js = '';
if ($can_add_address) {
    $baseUrl = rtrim(base_url(), '/');
    $extra_js = '<script>window.BASE_URL = ' . json_encode($baseUrl) . ';</script>'
        . '<script src="' . asset('js/cep-lookup.js') . '"></script>'
        . '<script src="' . asset('js/meus-enderecos.js') . '"></script>';
}
require __DIR__ . '/layout_store.php';
