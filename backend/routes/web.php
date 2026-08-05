<?php

use App\Controllers\HomeController;
use App\Controllers\StoreFrontController;
use App\Controllers\PanelController;
use App\Controllers\AnalyzingBIController;
use App\Http\Guard;

/**
 * Rotas de páginas: 'MÉTODO /caminho' => [Controller, método, requisito].
 * Mesmas regras da API — ver backend/routes/api.php.
 */
return [
    'GET /' => [HomeController::class, 'index', Guard::PUBLICO],
    'POST /login' => [HomeController::class, 'login', Guard::PUBLICO],
    'GET /sair' => [HomeController::class, 'logout', Guard::PUBLICO],
    'GET /minha-conta' => [HomeController::class, 'myAccount', Guard::AUTENTICADO],
    'POST /minha-conta/excluir' => [HomeController::class, 'deleteAccount', Guard::AUTENTICADO],
    'GET /lojas' => [HomeController::class, 'listStores', Guard::AUTENTICADO],
    'GET /criar-conta' => [HomeController::class, 'createAccountForm', Guard::PUBLICO],
    'POST /criar-conta' => [HomeController::class, 'createAccount', Guard::PUBLICO],
    'GET /criar-loja' => [HomeController::class, 'createStoreForm', Guard::AUTENTICADO],
    'POST /criar-loja' => [HomeController::class, 'createStore', Guard::AUTENTICADO],

    // Vitrine pública.
    'GET /loja/{slug}' => [StoreFrontController::class, 'vitrine', Guard::PUBLICO],
    'GET /loja/{slug}/categoria/{id}' => [StoreFrontController::class, 'categoria', Guard::PUBLICO],
    'GET /loja/{slug}/produto/{id}' => [StoreFrontController::class, 'product', Guard::PUBLICO],
    'GET /loja/{slug}/carrinho' => [StoreFrontController::class, 'cart', Guard::PUBLICO],
    // O comprovante aceita também quem tem o link com token, então a checagem
    // fica no controller (StoreFrontController::canViewOrder).
    'GET /loja/{slug}/pedido/{id}' => [StoreFrontController::class, 'order', Guard::PUBLICO],
    // Checkout e área do cliente exigem conta; o controller redireciona para o
    // login guardando o destino.
    'GET /loja/{slug}/checkout' => [StoreFrontController::class, 'checkout', Guard::PUBLICO],
    'GET /loja/{slug}/meus-pedidos' => [StoreFrontController::class, 'meusPedidos', Guard::PUBLICO],
    'GET /loja/{slug}/meus-enderecos' => [StoreFrontController::class, 'meusEnderecos', Guard::PUBLICO],

    // Painel.
    'GET /painel/{slug}' => [PanelController::class, 'dashboard', 'store.panel.view'],
    'GET /painel/{slug}/produtos' => [PanelController::class, 'products', 'store.catalog.read'],
    'GET /painel/{slug}/estoque' => [PanelController::class, 'stock', 'store.catalog.read'],
    'GET /painel/{slug}/entregas' => [PanelController::class, 'entregas', 'store.orders.read'],
    'GET /painel/{slug}/pdv' => [PanelController::class, 'pdv', 'store.pdv.operate'],
    'GET /painel/{slug}/funcionarios' => [PanelController::class, 'employees', 'store.users.manage'],
    'GET /painel/{slug}/hierarquia' => [PanelController::class, 'hierarchy', 'store.roles.read'],
    'GET /painel/{slug}/analyzing-bi' => [AnalyzingBIController::class, 'panel', 'store.bi.read'],
    'GET /painel/{slug}/configuracoes' => [PanelController::class, 'settings', 'store.settings.write'],
];
