<?php

use App\Controllers\HomeController;
use App\Controllers\StoreFrontController;
use App\Controllers\PanelController;
use App\Controllers\AnalyzingBIController;

return [
    'GET /' => [HomeController::class, 'index'],
    'POST /login' => [HomeController::class, 'login'],
    'GET /sair' => [HomeController::class, 'logout'],
    'GET /minha-conta' => [HomeController::class, 'myAccount'],
    'POST /minha-conta/excluir' => [HomeController::class, 'deleteAccount'],
    'GET /lojas' => [HomeController::class, 'listStores'],
    'GET /criar-conta' => [HomeController::class, 'createAccountForm'],
    'POST /criar-conta' => [HomeController::class, 'createAccount'],
    'GET /criar-loja' => [HomeController::class, 'createStoreForm'],
    'POST /criar-loja' => [HomeController::class, 'createStore'],

    'GET /loja/{slug}' => [StoreFrontController::class, 'vitrine'],
    'GET /loja/{slug}/produto/{id}' => [StoreFrontController::class, 'product'],
    'GET /loja/{slug}/carrinho' => [StoreFrontController::class, 'cart'],
    'GET /loja/{slug}/checkout' => [StoreFrontController::class, 'checkout'],
    'GET /loja/{slug}/pedido/{id}' => [StoreFrontController::class, 'order'],
    'GET /loja/{slug}/meus-pedidos' => [StoreFrontController::class, 'meusPedidos'],
    'GET /loja/{slug}/meus-enderecos' => [StoreFrontController::class, 'meusEnderecos'],

    'GET /painel/{slug}' => [PanelController::class, 'dashboard'],
    'GET /painel/{slug}/produtos' => [PanelController::class, 'products'],
    'GET /painel/{slug}/estoque' => [PanelController::class, 'stock'],
    'GET /painel/{slug}/entregas' => [PanelController::class, 'entregas'],
    'GET /painel/{slug}/pdv' => [PanelController::class, 'pdv'],
    'GET /painel/{slug}/funcionarios' => [PanelController::class, 'employees'],
    'GET /painel/{slug}/hierarquia' => [PanelController::class, 'hierarchy'],
    'GET /painel/{slug}/analyzing-bi' => [AnalyzingBIController::class, 'panel'],
    'GET /painel/{slug}/configuracoes' => [PanelController::class, 'settings'],
];
