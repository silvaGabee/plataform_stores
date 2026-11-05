<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\FavoriteRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\ShippingRuleRepository;
use App\Repositories\StoreRepository;
use App\Services\OrderPricing;
use App\Services\ShippingQuote;

/**
 * Vitrine: frete, favoritos e avaliações; e, no painel, as regras de frete,
 * o desconto à vista no PIX e o WhatsApp da loja.
 */
class VitrineExtrasApiController extends Controller
{
    // ------------------------------------------------------------ frete

    /** GET (público): ?cep=&subtotal= → cotação do frete. */
    public function shippingQuote(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $subtotal = max(0.0, round((float) str_replace(',', '.', (string) ($_GET['subtotal'] ?? '0')), 2));
        $cep = (string) ($_GET['cep'] ?? '');
        if (ShippingQuote::normalizeCep($cep) === null) {
            $this->json(['error' => 'Informe um CEP com 8 dígitos.'], 400);
            return;
        }
        $this->json(['frete' => $this->quote($store, $cep, $subtotal)]);
    }

    /** GET (gerente): faixas de frete e frete grátis. */
    public function listShipping(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $this->json($this->shippingPayload($store));
    }

    /** POST (gerente): {name, cep_start, cep_end, price, days?} */
    public function createShipping(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $nome = mb_substr(trim((string) ($in['name'] ?? '')), 0, 80);
        $ini = ShippingQuote::normalizeCep((string) ($in['cep_start'] ?? ''));
        $fim = ShippingQuote::normalizeCep((string) ($in['cep_end'] ?? ''));
        $preco = round((float) str_replace(',', '.', (string) ($in['price'] ?? '0')), 2);
        $diasRaw = trim((string) ($in['days'] ?? ''));
        $dias = $diasRaw === '' ? null : (int) $diasRaw;
        if ($nome === '') {
            $this->json(['error' => 'Dê um nome à faixa (ex.: "Belo Horizonte").'], 400);
            return;
        }
        if ($ini === null || $fim === null) {
            $this->json(['error' => 'CEP inicial e final precisam ter 8 dígitos.'], 400);
            return;
        }
        if (strcmp($ini, $fim) > 0) {
            [$ini, $fim] = [$fim, $ini];
        }
        if ($preco < 0 || $preco > 9999) {
            $this->json(['error' => 'Valor do frete inválido.'], 400);
            return;
        }
        if ($dias !== null && ($dias < 0 || $dias > 120)) {
            $this->json(['error' => 'Prazo entre 0 e 120 dias.'], 400);
            return;
        }
        (new ShippingRuleRepository())->create((int) $store['id'], $nome, $ini, $fim, $preco, $dias);
        $this->json(['success' => true] + $this->shippingPayload($store));
    }

    public function deleteShipping(string $slug, int $id): void
    {
        $store = $this->storeOr404($slug);
        (new ShippingRuleRepository())->delete($id, (int) $store['id']);
        $this->json(['success' => true] + $this->shippingPayload($store));
    }

    /** POST (gerente): {free_shipping_min: number|null} */
    public function saveFreeShipping(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $raw = $in['free_shipping_min'] ?? null;
        $min = $raw === null || $raw === '' ? null : round((float) str_replace(',', '.', (string) $raw), 2);
        if ($min !== null && ($min <= 0 || $min > 999999)) {
            $min = null;
        }
        (new StoreRepository())->updateFreeShippingMin((int) $store['id'], $min);
        $store = (new StoreRepository())->find((int) $store['id']) ?? $store;
        $this->json(['success' => true] + $this->shippingPayload($store));
    }

    // ------------------------------------------------------------ PIX

    /** POST (gerente): {pct} — desconto à vista no PIX. */
    public function savePixDiscount(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $pct = round((float) str_replace(',', '.', (string) ($in['pct'] ?? '0')), 2);
        if ($pct < 0 || $pct > OrderPricing::PIX_MAX_PCT) {
            $this->json(['error' => 'Desconto no PIX entre 0% e ' . (int) OrderPricing::PIX_MAX_PCT . '%.'], 400);
            return;
        }
        (new StoreRepository())->updatePixDiscount((int) $store['id'], $pct);
        $this->json(['success' => true, 'pct' => $pct]);
    }

    // ------------------------------------------------------------ WhatsApp

    /** POST (gerente): {phone} — número da loja (WhatsApp). Vazio remove. */
    public function savePhone(string $slug): void
    {
        $store = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $digitos = preg_replace('/\D/', '', (string) ($in['phone'] ?? '')) ?? '';
        if ($digitos !== '' && (strlen($digitos) < 10 || strlen($digitos) > 13)) {
            $this->json(['error' => 'Informe o número com DDD (ex.: 31 99999-9999).'], 400);
            return;
        }
        (new StoreRepository())->updatePhone((int) $store['id'], $digitos !== '' ? $digitos : null);
        $this->json(['success' => true, 'phone' => $digitos, 'whatsapp_url' => store_whatsapp_url(['phone' => $digitos])]);
    }

    // ------------------------------------------------------------ favoritos

    /** POST (cliente logado): liga/desliga o favorito. */
    public function toggleFavorite(string $slug, int $id): void
    {
        $store = $this->storeOr404($slug);
        $me = $this->requireLogin();
        if (!(new ProductRepository())->findByIdAndStore($id, (int) $store['id'])) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        $ativo = (new FavoriteRepository())->toggle((int) $me['id'], $id);
        $this->json(['success' => true, 'favorito' => $ativo]);
    }

    // ------------------------------------------------------------ avaliações

    /** POST (cliente que comprou): {rating 1–5, comment?} */
    public function saveReview(string $slug, int $id): void
    {
        $store = $this->storeOr404($slug);
        $me = $this->requireLogin();
        $storeId = (int) $store['id'];
        if (!(new ProductRepository())->findByIdAndStore($id, $storeId)) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        $repo = new ReviewRepository();
        if (!$repo->userBoughtProduct((int) $me['id'], $storeId, $id)) {
            $this->json(['error' => 'Só quem comprou este produto (pedido pago) pode avaliá-lo.'], 403);
            return;
        }
        $in = $this->getJsonInput();
        $nota = (int) ($in['rating'] ?? 0);
        if ($nota < 1 || $nota > 5) {
            $this->json(['error' => 'Escolha de 1 a 5 estrelas.'], 400);
            return;
        }
        $comentario = trim((string) ($in['comment'] ?? ''));
        $comentario = $comentario !== '' ? mb_substr($comentario, 0, 1000) : null;
        $repo->save($storeId, $id, (int) $me['id'], $nota, $comentario);
        $this->json(['success' => true, 'resumo' => $repo->summaryByProduct($id)]);
    }

    // ------------------------------------------------------------ apoio

    private function storeOr404(string $slug): array
    {
        $store = (new StoreRepository())->findBySlug($slug);
        if (!$store) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            exit;
        }

        return $store;
    }

    private function quote(array $store, string $cep, float $subtotal): array
    {
        $min = isset($store['free_shipping_min']) && $store['free_shipping_min'] !== null ? (float) $store['free_shipping_min'] : null;

        return ShippingQuote::quote((new ShippingRuleRepository())->listByStore((int) $store['id']), $min, $cep, $subtotal);
    }

    private function shippingPayload(array $store): array
    {
        $rules = array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'cep_start' => ShippingQuote::formatCep((string) $r['cep_start']),
                'cep_end' => ShippingQuote::formatCep((string) $r['cep_end']),
                'price' => (float) $r['price'],
                'days' => $r['days'] !== null ? (int) $r['days'] : null,
            ];
        }, (new ShippingRuleRepository())->listByStore((int) $store['id']));

        return [
            'rules' => $rules,
            'free_shipping_min' => isset($store['free_shipping_min']) && $store['free_shipping_min'] !== null ? (float) $store['free_shipping_min'] : null,
        ];
    }
}
