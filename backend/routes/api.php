<?php

use App\Controllers\AiController;
use App\Controllers\Api\StoreApiController;
use App\Controllers\Api\ProductApiController;
use App\Controllers\Api\OrderApiController;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\CashApiController;
use App\Controllers\Api\ReportApiController;
use App\Controllers\Api\UserApiController;
use App\Controllers\Api\RoleApiController;
use App\Controllers\Api\StockMovementApiController;
use App\Controllers\Api\CartApiController;
use App\Controllers\Api\CheckoutApiController;
use App\Controllers\Api\GoalsApiController;
use App\Controllers\Api\AnalyzingBIApiController;
use App\Controllers\Api\VitrineCategoryApiController;
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

    // Checkout: identidade sempre da sessão (ver Fase 0 em docs/).
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
    'GET /api/loja/{slug}/dashboard-config' => [StoreApiController::class, 'getDashboardConfig', 'store.settings.read'],
    'POST /api/loja/{slug}/dashboard-config' => [StoreApiController::class, 'updateDashboardConfig', 'store.settings.write'],
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
    'POST /api/loja/{slug}/products/delete' => [ProductApiController::class, 'deleteByBody', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/delete' => [ProductApiController::class, 'delete', 'store.catalog.write'],
    'POST /api/loja/{slug}/products/{id}/stock' => [ProductApiController::class, 'adjustStock', 'store.catalog.write'],

    'GET /api/loja/{slug}/orders/entregas' => [OrderApiController::class, 'listForEntregas', 'store.orders.read'],
    'GET /api/loja/{slug}/orders' => [OrderApiController::class, 'list', 'store.orders.read'],
    'GET /api/loja/{slug}/orders/{id}' => [OrderApiController::class, 'get', 'store.orders.read'],
    // Pedido da vitrine é criado pelo cliente logado; o controller distingue
    // online de PDV e aplica a regra de cada um.
    'POST /api/loja/{slug}/orders' => [OrderApiController::class, 'create', Guard::AUTENTICADO],
    'POST /api/loja/{slug}/orders/{id}/delivery-stage' => [OrderApiController::class, 'updateDeliveryStage', 'store.orders.write'],
    'DELETE /api/loja/{slug}/orders/{id}/entregas' => [OrderApiController::class, 'deleteFromEntregas', 'store.orders.write'],
    'POST /api/loja/{slug}/orders/{id}/entregas/delete' => [OrderApiController::class, 'deleteFromEntregas', 'store.orders.write'],

    // Pagamento do cliente: o controller exige posse do pedido (ver Fase 0).
    'POST /api/loja/{slug}/payments' => [PaymentApiController::class, 'create', Guard::AUTENTICADO],
    'GET /api/loja/{slug}/payments/pending' => [PaymentApiController::class, 'listPending', 'store.payments.read'],
    'GET /api/loja/{slug}/payments/{id}/status' => [PaymentApiController::class, 'status', Guard::AUTENTICADO],
    // Funcionário só confirma dinheiro de pedido PDV — regra dentro do controller,
    // porque depende do pagamento concreto.
    'POST /api/loja/{slug}/payments/confirm' => [PaymentApiController::class, 'confirm', 'store.payments.confirm'],

    'GET /api/loja/{slug}/cash/status' => [CashApiController::class, 'status', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/open' => [CashApiController::class, 'open', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/close' => [CashApiController::class, 'close', 'store.cash.operate'],
    'GET /api/loja/{slug}/cash/{id}/movements' => [CashApiController::class, 'movements', 'store.cash.operate'],
    'POST /api/loja/{slug}/cash/movements' => [CashApiController::class, 'addMovement', 'store.cash.operate'],

    'GET /api/loja/{slug}/reports/sales' => [ReportApiController::class, 'salesByPeriod', 'store.reports.read'],
    'GET /api/loja/{slug}/reports/top-products' => [ReportApiController::class, 'topProducts', 'store.reports.read'],
    'GET /api/loja/{slug}/reports/low-stock' => [ReportApiController::class, 'lowStock', 'store.reports.read'],
    'GET /api/loja/{slug}/reports/employees' => [ReportApiController::class, 'employeePerformance', 'store.reports.read'],
    'GET /api/loja/{slug}/reports/revenue' => [ReportApiController::class, 'revenue', 'store.reports.read'],

    'GET /api/loja/{slug}/analyzing-bi/faturamento' => [AnalyzingBIApiController::class, 'faturamento', 'store.bi.read'],
    'GET /api/{slug}/analyzing-bi/faturamento' => [AnalyzingBIApiController::class, 'faturamento', 'store.bi.read'],
    'GET /api/loja/{slug}/analyzing-bi' => [AnalyzingBIApiController::class, 'index', 'store.bi.read'],
    /** Alias do endpoint do BI (mesmo handler; slug = loja). */
    'GET /api/{slug}/analyzing-bi' => [AnalyzingBIApiController::class, 'index', 'store.bi.read'],

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

    'GET /api/loja/{slug}/stock-movements/product/{id}' => [StockMovementApiController::class, 'listByProduct', 'store.catalog.read'],
    'GET /api/loja/{slug}/stock-movements' => [StockMovementApiController::class, 'listByStore', 'store.catalog.read'],

    // O assistente consome uma API paga: nunca pode ficar aberto.
    'POST /api/loja/{slug}/ai/chat' => [AiController::class, 'chat', 'store.ai.use'],
    'POST /api/loja/{slug}/ai/descricao-produto' => [AiController::class, 'descricaoProduto', 'store.ai.use'],
    'GET /api/loja/{slug}/ai/reports/last30' => [AiController::class, 'last30Report', 'store.ai.use'],
    'GET /api/loja/{slug}/ai/snapshot' => [AiController::class, 'storeSnapshot', 'store.ai.use'],
    // A loja vem no corpo, não na URL, então o Guard não consegue resolvê-la:
    // a permissão é verificada dentro do controller, depois de ler o slug.
    'POST /api/ai/chat' => [AiController::class, 'chatGlobal', Guard::AUTENTICADO],
];
