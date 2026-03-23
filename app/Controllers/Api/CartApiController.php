<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;

class CartApiController extends Controller
{
    public function sync(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $input = $this->getJsonInput();
        $cart = $input['cart'] ?? [];
        if (!is_array($cart)) {
            $this->json(['error' => 'Carrinho inválido'], 400);
            return;
        }
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][$storeId] = $cart;
        $this->json(['success' => true]);
    }

    /** Limpa o carrinho da loja (ex.: após confirmar pagamento). */
    public function clear(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][$storeId] = [];
        $this->json(['success' => true]);
    }
}
