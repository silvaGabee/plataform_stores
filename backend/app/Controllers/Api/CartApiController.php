<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;

class CartApiController extends Controller
{
    /** Limites do carrinho guardado na sessão — o endpoint é público. */
    private const MAX_LINHAS = 100;
    private const MAX_QUANTIDADE = 999;

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
        if (count($cart) > self::MAX_LINHAS) {
            $this->json(['error' => 'Carrinho com itens demais.'], 400);
            return;
        }

        // Antes, o corpo era gravado na sessão como veio: qualquer visitante
        // anônimo podia mandar um JSON enorme e o arquivo de sessão crescia sem
        // limite, sem nunca virar um pedido. Aqui o carrinho é reduzido à sua
        // forma canônica — id, quantidade, variação — e nada além disso entra.
        $linhas = [];
        foreach (store_cart_normalize_lines($cart) as $linha) {
            if ($linha['product_id'] < 1) {
                continue;
            }
            $chave = store_cart_line_storage_key($linha['product_id'], $linha['variant_key']);
            // Quantidade é limitada aqui só para conter absurdo; o estoque real
            // é conferido na criação do pedido, contra o banco.
            $linhas[$chave] = min(self::MAX_QUANTIDADE, $linha['quantity']);
        }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][$storeId] = $linhas;
        $this->json(['success' => true, 'lines' => count($linhas)]);
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
