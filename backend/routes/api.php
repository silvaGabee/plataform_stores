<?php

use App\Controllers\AiController;
use App\Controllers\Api\StoreApiController;
use App\Controllers\Api\ProductApiController;
use App\Controllers\Api\OrderApiController;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\CashApiController;
use App\Controllers\Api\UserApiController;
use App\Controllers\Api\RoleApiController;
use App\Controllers\Api\StockMovementApiController;
use App\Controllers\Api\CartApiController;
use App\Controllers\Api\CheckoutApiController;
use App\Controllers\Api\GoalsApiController;
use App\Controllers\Api\AnalyzingBIApiController;
use App\Controllers\Api\VitrineCategoryApiController;
use App\Controllers\Api\FinanceApiController;
use App\Controllers\Api\PromotionApiController;
use App\Controllers\Api\VitrineExtrasApiController;
use App\Http\Guard;

/**
 * Rotas da API: 'MÉTODO /caminho' => [Controller, método, requisito de acesso].
 *
 * O terceiro elemento é OBRIGATÓRIO e é verificado pelo Guard antes de o
 * controller rodar. Rota sem ele levanta exceção em vez de executar — assim
 * endpoint novo nasce fechado, e não aberto por esquecimento.
 *
 *   Guard::PUBLICO       qualquer visitante
 *   Guard::AUTENTICADO   basta estar logado (a checagem fina fica no controller)
 *   'store.*'            permissão da matriz em App\Auth\Permissions
 *
 * Todo método que altera estado (POST/PUT/PATCH/DELETE) exige token CSRF,
 * independentemente do requisito.
 *
 * ATENÇÃO À ORDEM: as rotas são casadas em sequência, então caminhos fixos
 * precisam vir antes dos que têm parâmetro — 'products/low-stock' antes de
 * 'products/{id}', senão {id} engole "low-stock".
 */
return [
    // Carrinho: vive na sessão do visitante, sem conta.
    'POST /api/loja/{slug}/cart/sync' => [CartApiController::class, 'sync', Guard::PUBLICO],
    'POST /api/loja/{slug}/cart/clear' => [CartApiController::class, 'clear', Guard::PUBLICO],

    // Checkout: identidade sempre da sessão, nunca de parâmetro da URL.
    'GET /api/loja/{slug}/checkout/addresses' => [CheckoutApiController::class, 'addresses', Guard::AUTENTICADO],
    'POST /api/loja/{slug}/checkout/addresses' => [CheckoutApiController::class, 'createAddress', Guard::AUTENTICADO],
    'PUT /api/loja/{slug}/checkout/addresses/{id}' => [CheckoutApiController::class, 'updateAddress', Guard::AUTENTICADO],
    'DELETE /api/loja/{slug}/checkout/addresses/{id}' => [CheckoutApiController::class, 'deleteAddress', Guard::AUTENTICADO],

    // Dados públicos da loja (a vitrine depende deles).
    'GET /api/store/slug/{slug}' => [StoreApiController::class, 'getBySlug', Guard::PUBLICO],

    'POST /api/loja/{slug}/store/delete' => [StoreApiController::class, 'deleteStore', 'store.settings.write'],
    'POST /api/loja/{slug}/store/name' => [StoreApiController::class, 'updateStoreName', 'store.settings.write'],
    'POST /api/loja/{slug}/store/slogan' => [StoreApiController::class, 'updateStoreSlogan', 'store.settings.write'],
    'POST /api/loja/{slug}/store/background-color' => [StoreApiController::class, 'updateStoreBackgroundColor', 'store.settings.write'],
    'POST /api/loja/{slug}/store/appearance' => [StoreApiController::class, 'updateStoreAppearance', 'store.settings.write'],
    'GET /api/loja/{slug}/pix-config' => [StoreApiController::class, 'getPixConfig', 'store.settings.read'],
    'POST /api/loja/{slug}/pix-config' => [StoreApiController::class, 'updatePixConfig', 'store.settings.write'],
    'GET /api/loja/{slug}/banner' => [StoreApiController::class, 'getBanner', 'store.settings.read'],
    'POST /api/loja/{slug}/banner' => [StoreApiController::class, 'uploadBanner', 'store.settings.write'],
    'DELETE /api/loja/{slug}/banner' => [StoreApiController::class, 'deleteBanner', 'store.settings.write'],
    'GET /api/loja/{slug}/store-icon' => [StoreApiController::class, 'getStoreIcon', 'store.settings.read'],
    'POST /api/loja/{slug}/store-icon' => [StoreApiController::class, 'uploadStoreIcon', 'store.settings.write'],
    'DELETE /api/loja/{slug}/store-icon' => [StoreApiController::class, 'deleteStoreIcon', 'store.settings.write'],

    // Categorias da vitrine: leitura pública alimenta a loja; gestão é do painel.
    'GET /api/loja/{slug}/vitrine-categories/public' => [VitrineCategoryApiController::class, 'listPublic', Guard::PUBLICO],
    'GET /api/loja/{slug}/vitrine-home/conteudo' => [VitrineCategoryApiController::class, 'homeContentPublic', Guard::PUBLICO],
    'GET /api/loja/{slug}/vitrine-categories/{id}/conteudo' => [VitrineCategoryApiController::class, 'contentPublic', Guard::PUBLICO],
    'GET /api/loja/{slug}/vitrine-categories' => [VitrineCategoryApiController::class, 'list', 'store.catalog.read'],
    'POST /api/loja/{slug}/vitrine-categories' => [VitrineCategoryApiController::class, 'create', 'store.catalog.write'],
    'DELETE /api/loja/{slug}/vitrine-categories/{id}' => [VitrineCategoryApiController::class, 'delete', 'store.catalog.write'],

    'POST /api/loja/{slug}/product-image-delete' => [ProductApiController::class, 'deleteProductImageByBody', 'store.catalog.write'],
    'GET /api/loja/{slug}/products/low-stock' => [ProductApiController::class, 'lowStock', 'store.catalog.read'],
    'GET /api/loja/{slug}/products' => [ProductApiController::class, 'list', 'store.catalog.read'],
    'GET /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'get', 'store.catalog.read'],
    'POST /api/loja/{slug}/products' => [ProductApiController::class, 'create', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/images' => [ProductApiController::class, 'addImages', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/cover-image' => [ProductApiController::class, 'setCoverImage', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/images/delete' => [ProductApiController::class, 'deleteImage', 'store.catalog.write'],
    'PUT /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'update', 'store.catalog.write'],
    'DELETE /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'delete', 'store.catalog.write'],
    // POST .../products/delete (id no corpo) é o que o painel de Estoque chama;
    // o verbo DELETE acima serve a quem usa a API diretamente. O alias
    // POST .../products/{id}/delete foi removido por não ter nenhum chamador.
    'POST /api/loja/{slug}/products/delete' => [ProductApiController::class, 'deleteByBody', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/stock' => [ProductApiController::class, 'adjustStock', 'store.catalog.write'],

    'GET /api/loja/{slug}/orders/entregas' => [OrderApiController::class, 'listForEntregas', 'store.orders.read'],
    'GET /api/loja/{slug}/orders/aguardando-pagamento' => [OrderApiController::class, 'awaitingPayment', 'store.payments.read'],
    'GET /api/loja/{slug}/orders' => [OrderApiController::class, 'list', 'store.orders.read'],
    'GET /api/loja/{slug}/orders/{id}' => [OrderApiController::class, 'get', 'store.orders.read'],
    // Pedido da vitrine é criado pelo cliente logado; o controller distingue
    // online de PDV e aplica a regra de cada um.
    'POST /api/loja/{slug}/orders' => [OrderApiController::class, 'create', Guard::AUTENTICADO],
    'POST /api/loja/{slug}/orders/{id}/delivery-stage' => [OrderApiController::class, 'updateDeliveryStage', 'store.orders.write'],
    // "Remover do histórico" apagava a venda (DELETE FROM orders) e com ela o
    // faturamento do BI. Removido: o quadro já mostra só os últimos entregues.
    'POST /api/loja/{slug}/orders/{id}/cancel' => [OrderApiController::class, 'cancel', 'store.orders.cancel'],

    // Área do cliente: cancelar pedido não pago e retomar o pagamento PIX.
    'POST /api/loja/{slug}/meus-pedidos/{id}/cancelar' => [OrderApiController::class, 'cancelMine', Guard::AUTENTICADO],
    'GET /api/loja/{slug}/meus-pedidos/{id}/pagamento' => [OrderApiController::class, 'paymentOfMine', Guard::AUTENTICADO],

    // Descontos, cupons e parcelamento.
    'POST /api/loja/{slug}/pdv/descontos' => [PromotionApiController::class, 'requestDiscount', 'store.pdv.operate'],
    'GET /api/loja/{slug}/pdv/descontos/{id}' => [PromotionApiController::class, 'discountStatus', 'store.pdv.operate'],
    'POST /api/loja/{slug}/pdv/descontos/{id}/cancelar' => [PromotionApiController::class, 'cancelDiscount', 'store.pdv.operate'],
    'GET /api/loja/{slug}/descontos/pendentes' => [PromotionApiController::class, 'pendingDiscounts', 'store.discounts.approve'],
    'POST /api/loja/{slug}/descontos/{id}/decidir' => [PromotionApiController::class, 'decideDiscount', 'store.discounts.approve'],
    'GET /api/loja/{slug}/cupons' => [PromotionApiController::class, 'listCoupons', 'store.promotions.manage'],
    'POST /api/loja/{slug}/cupons' => [PromotionApiController::class, 'createCoupon', 'store.promotions.manage'],
    'POST /api/loja/{slug}/cupons/{id}/ativo' => [PromotionApiController::class, 'toggleCoupon', 'store.promotions.manage'],
    'POST /api/loja/{slug}/cupons/{id}/excluir' => [PromotionApiController::class, 'deleteCoupon', 'store.promotions.manage'],
    'POST /api/loja/{slug}/cupom/validar' => [PromotionApiController::class, 'validateCoupon', Guard::PUBLICO],
    'POST /api/loja/{slug}/products/{id}/promo' => [PromotionApiController::class, 'setPromo', 'store.promotions.manage'],
    'GET /api/loja/{slug}/parcelamento' => [PromotionApiController::class, 'installments', Guard::PUBLICO],
    'POST /api/loja/{slug}/store/installments' => [PromotionApiController::class, 'saveInstallmentRules', 'store.settings.write'],
    'POST /api/loja/{slug}/store/pix-discount' => [VitrineExtrasApiController::class, 'savePixDiscount', 'store.settings.write'],
    'POST /api/loja/{slug}/store/phone' => [VitrineExtrasApiController::class, 'savePhone', 'store.settings.write'],

    // Frete por CEP: cotação pública (carrinho/checkout), faixas no painel.
    'GET /api/loja/{slug}/frete' => [VitrineExtrasApiController::class, 'shippingQuote', Guard::PUBLICO],
    'GET /api/loja/{slug}/frete/faixas' => [VitrineExtrasApiController::class, 'listShipping', 'store.promotions.manage'],
    'POST /api/loja/{slug}/frete/faixas' => [VitrineExtrasApiController::class, 'createShipping', 'store.promotions.manage'],
    'POST /api/loja/{slug}/frete/faixas/{id}/excluir' => [VitrineExtrasApiController::class, 'deleteShipping', 'store.promotions.manage'],
    'POST /api/loja/{slug}/frete/gratis' => [VitrineExtrasApiController::class, 'saveFreeShipping', 'store.promotions.manage'],

    // Cliente logado: favoritos e avaliação (só de produto comprado — regra no controller).
    'POST /api/loja/{slug}/favoritos/{id}' => [VitrineExtrasApiController::class, 'toggleFavorite', Guard::AUTENTICADO],
    'POST /api/loja/{slug}/produtos/{id}/avaliacao' => [VitrineExtrasApiController::class, 'saveReview', Guard::AUTENTICADO],

    // Pagamento do cliente: o controller exige posse do pedido (ver Fase 0).
    'POST /api/loja/{slug}/payments' => [PaymentApiController::class, 'create', Guard::AUTENTICADO],
    'GET /api/loja/{slug}/payments/pending' => [PaymentApiController::class, 'listPending', 'store.payments.read'],
    'GET /api/loja/{slug}/payments/{id}/status' => [PaymentApiController::class, 'status', Guard::AUTENTICADO],
    // Funcionário só confirma pagamento de pedido PDV — regra dentro do
    // controller, porque depende do pedido concreto.
    'POST /api/loja/{slug}/payments/confirm' => [PaymentApiController::class, 'confirm', 'store.payments.confirm'],

    'GET /api/loja/{slug}/cash/status' => [CashApiController::class, 'status', 'store.cash.operate'],
    'GET /api/loja/{slug}/cash/summary' => [CashApiController::class, 'summary', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/open' => [CashApiController::class, 'open', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/close' => [CashApiController::class, 'close', 'store.cash.operate'],
    'GET /api/loja/{slug}/cash/closings' => [CashApiController::class, 'closings', 'store.payments.read'],
    'GET /api/loja/{slug}/cash/{id}/movements' => [CashApiController::class, 'movements', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/movements' => [CashApiController::class, 'addMovement', 'store.cash.operate'],

    // Simulador de parcelamento (tabela Price) usado no PDV. Só calcula.
    'GET /api/loja/{slug}/finance/installments' => [FinanceApiController::class, 'installments', 'store.pdv.operate'],

    // Os aliases sem /loja/ (GET /api/{slug}/analyzing-bi) foram removidos:
    // nada no front os chamava, e um padrão de rota tão genérico competia com
    // qualquer caminho de um segmento sob /api/.
    'GET /api/loja/{slug}/analyzing-bi/faturamento' => [AnalyzingBIApiController::class, 'faturamento', 'store.bi.read'],
    'GET /api/loja/{slug}/analyzing-bi' => [AnalyzingBIApiController::class, 'index', 'store.bi.read'],

    'GET /api/loja/{slug}/goals' => [GoalsApiController::class, 'get', 'store.goals.read'],
    'POST /api/loja/{slug}/goals/store' => [GoalsApiController::class, 'setStoreGoal', 'store.goals.write'],
    'POST /api/loja/{slug}/goals/employee' => [GoalsApiController::class, 'setEmployeeGoal', 'store.goals.write'],

    'GET /api/loja/{slug}/users' => [UserApiController::class, 'list', 'store.users.manage'],
    'POST /api/loja/{slug}/users' => [UserApiController::class, 'create', 'store.users.manage'],
    'POST /api/loja/{slug}/users/delete' => [UserApiController::class, 'deleteByBody', 'store.users.manage'],
    'PUT /api/loja/{slug}/users/{id}' => [UserApiController::class, 'update', 'store.users.manage'],
    'DELETE /api/loja/{slug}/users/{id}' => [UserApiController::class, 'delete', 'store.users.manage'],
    'GET /api/loja/{slug}/users/{id}/roles' => [UserApiController::class, 'getRoles', 'store.users.manage'],
    'POST /api/loja/{slug}/users/{id}/roles' => [UserApiController::class, 'assignRoles', 'store.users.manage'],

    'GET /api/loja/{slug}/roles/hierarchy' => [RoleApiController::class, 'hierarchy', 'store.roles.read'],
    'GET /api/loja/{slug}/roles' => [RoleApiController::class, 'list', 'store.roles.read'],
    'POST /api/loja/{slug}/roles/seed-example' => [RoleApiController::class, 'seedExample', 'store.roles.manage'],
    'POST /api/loja/{slug}/roles' => [RoleApiController::class, 'create', 'store.roles.manage'],
    'PUT /api/loja/{slug}/roles/{id}' => [RoleApiController::class, 'update', 'store.roles.manage'],
    'DELETE /api/loja/{slug}/roles/{id}' => [RoleApiController::class, 'delete', 'store.roles.manage'],

    // Histórico de estoque: só o gerente acompanha quem moveu o quê.
    'GET /api/loja/{slug}/stock-movements/search' => [StockMovementApiController::class, 'search', 'store.stock.history'],
    'GET /api/loja/{slug}/stock-movements/product/{id}' => [StockMovementApiController::class, 'listByProduct', 'store.stock.history'],
    'GET /api/loja/{slug}/stock-movements' => [StockMovementApiController::class, 'listByStore', 'store.stock.history'],

    // O assistente consome uma API paga: nunca pode ficar aberto.
    'POST /api/loja/{slug}/ai/chat' => [AiController::class, 'chat', 'store.ai.use'],
    'POST /api/loja/{slug}/ai/descricao-produto' => [AiController::class, 'descricaoProduto', 'store.ai.use'],
    // A loja vem no corpo, não na URL, então o Guard não consegue resolvê-la:
    // a permissão é verificada dentro do controller, depois de ler o slug.
    'POST /api/ai/chat' => [AiController::class, 'chatGlobal', Guard::AUTENTICADO],
];
