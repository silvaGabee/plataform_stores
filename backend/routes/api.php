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

return [
    'POST /api/loja/{slug}/cart/sync' => [CartApiController::class, 'sync'],
    'POST /api/loja/{slug}/cart/clear' => [CartApiController::class, 'clear'],

    'GET /api/loja/{slug}/checkout/addresses' => [CheckoutApiController::class, 'addresses'],
    'POST /api/loja/{slug}/checkout/addresses' => [CheckoutApiController::class, 'createAddress'],
    'PUT /api/loja/{slug}/checkout/addresses/{id}' => [CheckoutApiController::class, 'updateAddress'],
    'DELETE /api/loja/{slug}/checkout/addresses/{id}' => [CheckoutApiController::class, 'deleteAddress'],

    'GET /api/store/slug/{slug}' => [StoreApiController::class, 'getBySlug'],
    'POST /api/store' => [StoreApiController::class, 'create'],
    'POST /api/loja/{slug}/store/delete' => [StoreApiController::class, 'deleteStore'],
    'GET /api/loja/{slug}/pix-config' => [StoreApiController::class, 'getPixConfig'],
    'POST /api/loja/{slug}/pix-config' => [StoreApiController::class, 'updatePixConfig'],
    'GET /api/loja/{slug}/dashboard-config' => [StoreApiController::class, 'getDashboardConfig'],
    'POST /api/loja/{slug}/dashboard-config' => [StoreApiController::class, 'updateDashboardConfig'],
    'GET /api/loja/{slug}/banner' => [StoreApiController::class, 'getBanner'],
    'POST /api/loja/{slug}/banner' => [StoreApiController::class, 'uploadBanner'],
    'DELETE /api/loja/{slug}/banner' => [StoreApiController::class, 'deleteBanner'],
    'GET /api/loja/{slug}/store-icon' => [StoreApiController::class, 'getStoreIcon'],
    'POST /api/loja/{slug}/store-icon' => [StoreApiController::class, 'uploadStoreIcon'],
    'DELETE /api/loja/{slug}/store-icon' => [StoreApiController::class, 'deleteStoreIcon'],

    'POST /api/loja/{slug}/product-image-delete' => [ProductApiController::class, 'deleteProductImageByBody'],
    'GET /api/loja/{slug}/products' => [ProductApiController::class, 'list'],
    'GET /api/loja/{slug}/products/low-stock' => [ProductApiController::class, 'lowStock'],
    'GET /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'get'],
    'POST /api/loja/{slug}/products' => [ProductApiController::class, 'create'],
    'POST /api/loja/{slug}/products/{id}/images' => [ProductApiController::class, 'addImages'],
    'POST /api/loja/{slug}/products/{id}/images/delete' => [ProductApiController::class, 'deleteImage'],
    'PUT /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'update'],
    'DELETE /api/loja/{slug}/products/{id}' => [ProductApiController::class, 'delete'],
    'POST /api/loja/{slug}/products/delete' => [ProductApiController::class, 'deleteByBody'],
    'POST /api/loja/{slug}/products/{id}/delete' => [ProductApiController::class, 'delete'],
    'POST /api/loja/{slug}/products/{id}/stock' => [ProductApiController::class, 'adjustStock'],

    'GET /api/loja/{slug}/orders' => [OrderApiController::class, 'list'],
    'GET /api/loja/{slug}/orders/entregas' => [OrderApiController::class, 'listForEntregas'],
    'GET /api/loja/{slug}/orders/{id}' => [OrderApiController::class, 'get'],
    'POST /api/loja/{slug}/orders' => [OrderApiController::class, 'create'],
    'POST /api/loja/{slug}/orders/{id}/delivery-stage' => [OrderApiController::class, 'updateDeliveryStage'],

    'POST /api/loja/{slug}/payments' => [PaymentApiController::class, 'create'],
    'GET /api/loja/{slug}/payments/pending' => [PaymentApiController::class, 'listPending'],
    'GET /api/loja/{slug}/payments/{id}/status' => [PaymentApiController::class, 'status'],
    'POST /api/loja/{slug}/payments/confirm' => [PaymentApiController::class, 'confirm'],

    'GET /api/loja/{slug}/cash/status' => [CashApiController::class, 'status'],
    'POST /api/loja/{slug}/cash/open' => [CashApiController::class, 'open'],
    'POST /api/loja/{slug}/cash/close' => [CashApiController::class, 'close'],
    'GET /api/loja/{slug}/cash/{id}/movements' => [CashApiController::class, 'movements'],
    'POST /api/loja/{slug}/cash/movements' => [CashApiController::class, 'addMovement'],

    'GET /api/loja/{slug}/reports/sales' => [ReportApiController::class, 'salesByPeriod'],
    'GET /api/loja/{slug}/reports/top-products' => [ReportApiController::class, 'topProducts'],
    'GET /api/loja/{slug}/reports/low-stock' => [ReportApiController::class, 'lowStock'],
    'GET /api/loja/{slug}/reports/employees' => [ReportApiController::class, 'employeePerformance'],
    'GET /api/loja/{slug}/reports/revenue' => [ReportApiController::class, 'revenue'],
    'GET /api/loja/{slug}/reports/customers' => [ReportApiController::class, 'customers'],

    'GET /api/loja/{slug}/analyzing-bi/faturamento' => [AnalyzingBIApiController::class, 'faturamento'],
    'GET /api/{slug}/analyzing-bi/faturamento' => [AnalyzingBIApiController::class, 'faturamento'],
    'GET /api/loja/{slug}/analyzing-bi' => [AnalyzingBIApiController::class, 'index'],
    /** Alias do endpoint do BI (mesmo handler; slug = loja). */
    'GET /api/{slug}/analyzing-bi' => [AnalyzingBIApiController::class, 'index'],

    'GET /api/loja/{slug}/goals' => [GoalsApiController::class, 'get'],
    'POST /api/loja/{slug}/goals/store' => [GoalsApiController::class, 'setStoreGoal'],
    'POST /api/loja/{slug}/goals/employee' => [GoalsApiController::class, 'setEmployeeGoal'],

    'GET /api/loja/{slug}/users' => [UserApiController::class, 'list'],
    'POST /api/loja/{slug}/users' => [UserApiController::class, 'create'],
    'POST /api/loja/{slug}/users/delete' => [UserApiController::class, 'deleteByBody'],
    'PUT /api/loja/{slug}/users/{id}' => [UserApiController::class, 'update'],
    'DELETE /api/loja/{slug}/users/{id}' => [UserApiController::class, 'delete'],
    'GET /api/loja/{slug}/users/{id}/roles' => [UserApiController::class, 'getRoles'],
    'POST /api/loja/{slug}/users/{id}/roles' => [UserApiController::class, 'assignRoles'],

    'GET /api/loja/{slug}/roles' => [RoleApiController::class, 'list'],
    'GET /api/loja/{slug}/roles/hierarchy' => [RoleApiController::class, 'hierarchy'],
    'POST /api/loja/{slug}/roles/seed-example' => [RoleApiController::class, 'seedExample'],
    'POST /api/loja/{slug}/roles' => [RoleApiController::class, 'create'],
    'PUT /api/loja/{slug}/roles/{id}' => [RoleApiController::class, 'update'],
    'DELETE /api/loja/{slug}/roles/{id}' => [RoleApiController::class, 'delete'],

    'GET /api/loja/{slug}/stock-movements' => [StockMovementApiController::class, 'listByStore'],
    'GET /api/loja/{slug}/stock-movements/product/{id}' => [StockMovementApiController::class, 'listByProduct'],

    'POST /api/ai/chat' => [AiController::class, 'chatGlobal'],
    'POST /api/loja/{slug}/ai/chat' => [AiController::class, 'chat'],
    'POST /api/loja/{slug}/ai/descricao-produto' => [AiController::class, 'descricaoProduto'],
];
