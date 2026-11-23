<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;

class CartApiController extends Controller
{
    /** Limites do carrinho guardado na sessão — o endpoint é público. */
    private const MAX_LINHAS = 100;
    /** Acima disto o pedido é recusado inteiro; entre 100 e isto, guarda as 100 primeiras. */
    private const MAX_LINHAS_RECEBIDAS = 1000;
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
        // Muito acima do que alguém monta à mão: aí é abuso, e nada é gravado.
        if (count($cart) > self::MAX_LINHAS_RECEBIDAS) {
            $this->json(['error' => 'Carrinho com itens demais.'], 400);
            return;
        }

        // Antes, o corpo era gravado na sessão como veio: qualquer visitante
        // anônimo podia mandar um JSON enorme e o arquivo de sessão crescia sem
        // limite, sem nunca virar um pedido. Aqui o carrinho é reduzido à sua
        // forma canônica — id, quantidade, variação — e nada além disso entra.
        $normalizadas = array_values(array_filter(
            store_cart_normalize_lines($cart),
            static fn (array $l): bool => $l['product_id'] > 0
        ));
        // Produto apagado (ou de outra loja) sai daqui, e a resposta conta
        // quantos saíram. Antes o servidor só o ignorava ao montar a página: o
        // carrinho aparecia vazio e o ícone, lido do navegador, seguia com número.
        $existem = (new \App\Repositories\ProductRepository())->idsDaLoja(
            $storeId,
            array_map(static fn (array $l): int => $l['product_id'], $normalizadas)
        );
        $linhas = [];
        $removidos = 0;
        $cortados = 0;
        foreach ($normalizadas as $linha) {
            if (!isset($existem[$linha['product_id']])) {
                $removidos++;
                continue;
            }
            $chave = store_cart_line_storage_key($linha['product_id'], $linha['variant_key']);
            if (!isset($linhas[$chave]) && count($linhas) >= self::MAX_LINHAS) {
                $cortados++;
                continue;
            }
            // Quantidade é limitada aqui só para conter absurdo; o estoque real
            // é conferido na criação do pedido, contra o banco.
            $linhas[$chave] = min(self::MAX_QUANTIDADE, $linha['quantity']);
        }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][$storeId] = $linhas;
        // `cart` é o carrinho que ficou valendo: o navegador o copia para que
        // o ícone mostre o mesmo que a página.
        $this->json([
            'success' => true,
            'lines' => count($linhas),
            'cart' => (object) $linhas,
            'removidos' => $removidos,
            'cortados' => $cortados,
        ]);
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
