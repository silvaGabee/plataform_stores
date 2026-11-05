<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\CouponRepository;
use App\Repositories\DiscountRequestRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Services\InstallmentPlan;
use App\Services\ProductService;
use App\Repositories\StockMovementRepository;
use App\Repositories\ProductImageRepository;

/**
 * Descontos e condições de pagamento:
 *   - pedidos de desconto do PDV (funcionário pede, gerente decide);
 *   - cupons da vitrine (gerente cria; cliente valida no checkout);
 *   - preço promocional por produto (gerente);
 *   - regra de parcelamento da loja (gerente) e simulação pública.
 */
class PromotionApiController extends Controller
{
    // ------------------------------------------------------------ PDV

    /** POST (pdv.operate): {subtotal, discount, reason?, items_summary?} */
    public function requestDiscount(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $me = (int) ($_SESSION['logged_user_id'] ?? 0);
        $in = $this->getJsonInput();
        $subtotal = round((float) ($in['subtotal'] ?? 0), 2);
        $discount = round((float) ($in['discount'] ?? 0), 2);
        if ($subtotal <= 0 || $discount <= 0 || $discount > $subtotal) {
            $this->json(['error' => 'Informe um desconto maior que zero e menor que o total da venda.'], 400);
        }
        $repo = new DiscountRequestRepository();
        $id = $repo->create([
            'store_id' => $storeId,
            'requested_by' => $me,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'reason' => mb_substr(trim((string) ($in['reason'] ?? '')), 0, 255) ?: null,
            'items_summary' => mb_substr(trim((string) ($in['items_summary'] ?? '')), 0, 500) ?: null,
        ]);
        $this->json(['success' => true, 'request' => $repo->find($id, $storeId)]);
    }

    /** GET (pdv.operate): situação de um pedido de desconto do próprio operador. */
    public function discountStatus(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        $repo = new DiscountRequestRepository();
        $repo->expire($storeId);
        $r = $repo->find($id, $storeId);
        if (!$r || ((int) $r['requested_by'] !== (int) ($_SESSION['logged_user_id'] ?? 0) && !is_gerente_store($storeId))) {
            $this->json(['error' => 'Solicitação não encontrada'], 404);
        }
        $this->json(['request' => $r]);
    }

    /** POST (pdv.operate): o operador desiste do pedido de desconto. */
    public function cancelDiscount(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        (new DiscountRequestRepository())->cancel($id, $storeId, (int) ($_SESSION['logged_user_id'] ?? 0));
        $this->json(['success' => true]);
    }

    /** GET (store.discounts.approve): pendentes + últimas decisões. */
    public function pendingDiscounts(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $repo = new DiscountRequestRepository();
        $this->json([
            'pending' => $repo->listPending($storeId),
            'recent' => isset($_GET['historico']) ? $repo->listRecent($storeId, 20) : [],
        ]);
    }

    /** POST (store.discounts.approve): {decision: aprovar|recusar} */
    public function decideDiscount(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $status = ($in['decision'] ?? '') === 'aprovar' ? 'aprovado' : (($in['decision'] ?? '') === 'recusar' ? 'recusado' : '');
        if ($status === '') {
            $this->json(['error' => 'Decisão inválida.'], 400);
        }
        $repo = new DiscountRequestRepository();
        $repo->expire($storeId);
        if (!$repo->decide($id, $storeId, $status, (int) ($_SESSION['logged_user_id'] ?? 0))) {
            $this->json(['error' => 'Esta solicitação já foi respondida, cancelada ou expirou.'], 409);
        }
        $this->json(['success' => true, 'request' => $repo->find($id, $storeId)]);
    }

    // ------------------------------------------------------------ cupons

    public function listCoupons(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $this->json(['coupons' => (new CouponRepository())->listByStore($storeId)]);
    }

    /** POST: {code, type: percent|valor, value, min_subtotal?, max_uses?, ends_at?} */
    public function createCoupon(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $code = CouponRepository::normalizeCode((string) ($in['code'] ?? ''));
        $type = ($in['type'] ?? 'percent') === 'valor' ? 'valor' : 'percent';
        $value = round((float) ($in['value'] ?? 0), 2);
        if (strlen($code) < 3 || strlen($code) > 30) {
            $this->json(['error' => 'O código precisa ter de 3 a 30 letras ou números.'], 400);
        }
        if ($value <= 0 || ($type === 'percent' && $value > 90)) {
            $this->json(['error' => $type === 'percent' ? 'Percentual entre 1% e 90%.' : 'Informe um valor maior que zero.'], 400);
        }
        $endsAt = trim((string) ($in['ends_at'] ?? ''));
        if ($endsAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt)) {
            $this->json(['error' => 'Data de validade inválida.'], 400);
        }
        $repo = new CouponRepository();
        if ($repo->findByCode($storeId, $code)) {
            $this->json(['error' => 'Já existe um cupom com esse código.'], 400);
        }
        $maxUses = (int) ($in['max_uses'] ?? 0);
        $repo->create($storeId, [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'min_subtotal' => max(0, round((float) ($in['min_subtotal'] ?? 0), 2)),
            'max_uses' => $maxUses > 0 ? $maxUses : null,
            'ends_at' => $endsAt !== '' ? $endsAt : null,
        ]);
        $this->json(['success' => true, 'coupons' => $repo->listByStore($storeId)]);
    }

    public function toggleCoupon(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        (new CouponRepository())->setActive($id, $storeId, !empty($in['active']));
        $this->json(['success' => true]);
    }

    public function deleteCoupon(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        (new CouponRepository())->delete($id, $storeId);
        $this->json(['success' => true]);
    }

    /**
     * POST (público): {code, items:[{product_id, quantity}]} → desconto.
     * O subtotal é recalculado aqui com os preços do banco.
     */
    public function validateCoupon(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $subtotal = $this->subtotalDosItens($storeId, is_array($in['items'] ?? null) ? $in['items'] : []);
        try {
            $r = (new CouponRepository())->evaluate($storeId, (string) ($in['code'] ?? ''), $subtotal);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }
        $c = $r['coupon'];
        $this->json([
            'success' => true,
            'code' => $c['code'],
            'label' => $c['type'] === 'percent'
                ? rtrim(rtrim(number_format((float) $c['value'], 2, ',', ''), '0'), ',') . '% de desconto'
                : 'R$ ' . number_format((float) $c['value'], 2, ',', '.') . ' de desconto',
            'subtotal' => $subtotal,
            'discount' => $r['discount'],
            'total' => round($subtotal - $r['discount'], 2),
        ]);
    }

    // ------------------------------------------------------------ promoção por produto

    /** POST (gerente): {promo_price: number|null} */
    public function setPromo(string $slug, int $id): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $svc = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        try {
            $p = $svc->update($id, $storeId, ['promo_price' => $in['promo_price'] ?? null]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }
        if (!$p) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        $this->json(['success' => true, 'product' => ['id' => (int) $p['id'], 'sale_price' => (float) $p['sale_price'], 'promo_price' => $p['promo_price'] !== null ? (float) $p['promo_price'] : null]]);
    }

    // ------------------------------------------------------------ parcelamento

    /** GET (público): ?valor= → opções de parcelamento da loja. */
    public function installments(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $store = (new StoreRepository())->find($storeId) ?? [];
        $valor = round((float) str_replace(',', '.', (string) ($_GET['valor'] ?? '0')), 2);
        $rules = InstallmentPlan::rules($store);
        $pct = \App\Services\OrderPricing::pixPct($store);
        $this->json([
            'rules' => $rules,
            'options' => InstallmentPlan::options($store, $valor),
            // Desconto à vista no PIX e quanto ele "custa" em juros ao mês,
            // comparado a parcelar sem juros no limite da loja.
            'pix' => [
                'pct' => $pct,
                'preco' => \App\Services\OrderPricing::pixPrice($store, $valor),
                'taxa_equivalente' => \App\Domain\Finance\CashDiscount::taxaEquivalente($pct, $rules['free']),
                'parcelas_sem_juros' => $rules['free'],
            ],
        ]);
    }

    /** POST (gerente): {max, free, rate, min_value} */
    public function saveInstallmentRules(string $slug): void
    {
        $storeId = $this->storeOr404($slug);
        $in = $this->getJsonInput();
        $max = (int) ($in['max'] ?? 6);
        $free = (int) ($in['free'] ?? 1);
        $rate = round((float) ($in['rate'] ?? 0), 2);
        $min = round((float) ($in['min_value'] ?? 0), 2);
        if ($max < 1 || $max > InstallmentPlan::MAX) {
            $this->json(['error' => 'Parcelas: de 1 a ' . InstallmentPlan::MAX . '.'], 400);
        }
        if ($free < 1 || $free > $max) {
            $this->json(['error' => 'As parcelas sem juros precisam estar entre 1 e o máximo.'], 400);
        }
        if ($rate < 0 || $rate > 15) {
            $this->json(['error' => 'Taxa de juros entre 0% e 15% ao mês.'], 400);
        }
        if ($min < 0) {
            $this->json(['error' => 'Parcela mínima inválida.'], 400);
        }
        (new StoreRepository())->updateInstallmentRules($storeId, $max, $free, $rate, $min);
        $store = (new StoreRepository())->find($storeId) ?? [];
        $this->json(['success' => true, 'rules' => InstallmentPlan::rules($store)]);
    }

    // ------------------------------------------------------------ apoio

    private function storeOr404(string $slug): int
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            exit;
        }

        return $storeId;
    }

    private function subtotalDosItens(int $storeId, array $items): float
    {
        $repo = new ProductRepository();
        $total = 0.0;
        foreach (array_slice($items, 0, 50) as $it) {
            $p = $repo->findByIdAndStore((int) ($it['product_id'] ?? 0), $storeId);
            $q = max(0, min(99, (int) ($it['quantity'] ?? 0)));
            if ($p && $q > 0) {
                $total += product_effective_price($p) * $q;
            }
        }

        return round($total, 2);
    }
}
