<?php
/**
 * Rodapé da vitrine.
 *
 * Antes era só "Nome da loja © ano". Agora mostra o que o cliente procura
 * antes de comprar (contato, formas de pagamento, entrega, trocas) e os dados
 * da empresa que o Decreto 7.962/2013 pede no comércio eletrônico. Cada item
 * só aparece se a loja tiver o dado cadastrado.
 *
 * @var array $store
 */
$__fPix = \App\Services\OrderPricing::pixPct($store);
$__fParcelas = \App\Services\InstallmentPlan::rules($store);
$__fFreteMin = isset($store['free_shipping_min']) && $store['free_shipping_min'] !== null ? (float) $store['free_shipping_min'] : null;
$__fPhone = trim((string) ($store['phone'] ?? ''));
$__fWa = store_whatsapp_url($store, 'Olá! Vim pela loja ' . $store['name'] . ' e tenho uma dúvida.');
$__fCity = trim((string) ($store['city'] ?? ''));
$__fLegal = trim((string) ($store['legal_name'] ?? ''));
$__fDoc = trim((string) ($store['document'] ?? ''));
$__fAddress = trim((string) ($store['address'] ?? ''));
$__fDocLabel = strlen(preg_replace('/\D/', '', $__fDoc) ?? '') === 11 ? 'CPF' : 'CNPJ';
?>
<footer class="store-footer store-footer--full">
    <div class="container store-footer-grid">
        <section class="store-footer-col store-footer-brand" aria-label="Sobre a loja">
            <p class="store-footer-name"><?= htmlspecialchars($store['name']) ?></p>
            <?php if (trim((string) ($store['slogan'] ?? '')) !== ''): ?>
                <p class="store-footer-text"><?= htmlspecialchars((string) $store['slogan']) ?></p>
            <?php endif; ?>
            <?php if ($__fAddress !== ''): ?>
                <p class="store-footer-text"><?= ui_icon('pin', 15) ?> <?= htmlspecialchars($__fAddress) ?></p>
            <?php elseif ($__fCity !== ''): ?>
                <p class="store-footer-text"><?= ui_icon('pin', 15) ?> <?= htmlspecialchars($__fCity) ?></p>
            <?php endif; ?>
        </section>

        <?php if ($__fWa !== null || $__fPhone !== ''): ?>
            <section class="store-footer-col" aria-labelledby="store-footer-contato">
                <h2 class="store-footer-title" id="store-footer-contato">Atendimento</h2>
                <ul class="store-footer-list">
                    <?php if ($__fWa !== null): ?>
                        <li><a href="<?= htmlspecialchars($__fWa) ?>" target="_blank" rel="noopener"><?= ui_icon('chat', 15) ?> WhatsApp <?= htmlspecialchars(store_phone_format($__fPhone)) ?></a></li>
                    <?php else: ?>
                        <li><?= ui_icon('chat', 15) ?> <?= htmlspecialchars(store_phone_format($__fPhone)) ?></li>
                    <?php endif; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="store-footer-col" aria-labelledby="store-footer-pagamento">
            <h2 class="store-footer-title" id="store-footer-pagamento">Pagamento e entrega</h2>
            <ul class="store-footer-list">
                <li><?= ui_icon('qr', 15) ?> PIX<?= $__fPix > 0 ? ' com ' . rtrim(rtrim(number_format($__fPix, 2, ',', ''), '0'), ',') . '% de desconto' : '' ?></li>
                <li><?= ui_icon('card', 15) ?> Cartão de crédito<?= $__fParcelas['free'] > 1 ? ' em até ' . $__fParcelas['free'] . 'x sem juros' : ($__fParcelas['max'] > 1 ? ' em até ' . $__fParcelas['max'] . 'x' : '') ?></li>
                <li><?= ui_icon('truck', 15) ?> Entrega ou retirada na loja<?= $__fFreteMin !== null && $__fFreteMin > 0 ? '. Frete grátis acima de R$ ' . number_format($__fFreteMin, 2, ',', '.') : '' ?></li>
            </ul>
        </section>

        <section class="store-footer-col" aria-labelledby="store-footer-conta">
            <h2 class="store-footer-title" id="store-footer-conta">Sua conta</h2>
            <ul class="store-footer-list">
                <li><a href="<?= base_url("loja/{$store['slug']}/meus-pedidos") ?>">Meus pedidos</a></li>
                <li><a href="<?= base_url("loja/{$store['slug']}/favoritos") ?>">Meus favoritos</a></li>
                <li><a href="<?= base_url("loja/{$store['slug']}/meus-enderecos") ?>">Meus endereços</a></li>
            </ul>
        </section>
    </div>

    <div class="container store-footer-legal">
        <p class="store-footer-policy"><?= ui_icon('shield', 15) ?> <span><strong>Trocas e devoluções:</strong> você pode desistir da compra em até 7 dias após o recebimento (art. 49 do Código de Defesa do Consumidor). Fale com a loja para combinar a devolução.</span></p>
        <p class="store-footer-company">
            <span><?= htmlspecialchars($__fLegal !== '' ? $__fLegal : $store['name']) ?> &copy; <?= date('Y') ?></span>
            <?php if ($__fDoc !== ''): ?><span><?= $__fDocLabel ?> <?= htmlspecialchars(store_document_format($__fDoc)) ?></span><?php endif; ?>
            <a href="<?= base_url('privacidade') ?>">Privacidade e termos de uso</a>
        </p>
    </div>
</footer>
