<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PixService;
use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;
use App\Repositories\StorePixConfigRepository;

class PaymentApiController extends Controller
{
    private function orderService(): OrderService
    {
        return new OrderService(
            new OrderRepository(),
            new OrderItemRepository(),
            new ProductRepository(),
            new PaymentRepository(),
            new StockMovementRepository(),
            new CashRegisterRepository(),
            new CashMovementRepository()
        );
    }

    public function create(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $orderId = (int) ($input['order_id'] ?? 0);
        $order = $orderId ? (new OrderRepository())->findByIdAndStore($orderId, $storeId) : null;
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        $orderType = strtolower((string) ($order['order_type'] ?? 'online'));
        if ($orderType === 'pdv') {
            $this->requireStorePanelAccess($storeId);
        }
        $method = $input['method'] ?? 'pix';
        if (!in_array($method, ['pix', 'dinheiro', 'cartao'])) {
            $this->json(['error' => 'Método inválido'], 400);
            return;
        }
        $amount = (float) $order['total'];
        $pixQr = null;
        $pixManual = null;
        if ($method === 'pix') {
            $pixConfigRepo = new StorePixConfigRepository();
            $pixService = new PixService($pixConfigRepo);
            $pixQr = $pixService->generateQrCode($storeId, $amount, 'Pedido #' . $orderId);
            if ($pixQr === null) {
                $config = $pixConfigRepo->findByStore($storeId);
                if (!empty($config['pix_key'])) {
                    $pixManual = [
                        'chave' => $config['pix_key'],
                        'valor' => $amount,
                        'nome' => $config['merchant_name'] ?? 'Loja',
                    ];
                }
            }
        }
        try {
            $payment = $this->orderService()->addPayment($orderId, $storeId, $method, $amount, $pixQr);
            if ($pixManual !== null) {
                $payment['pix_manual'] = $pixManual;
            }
            $this->json(['success' => true, 'payment' => $payment]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function status(string $slug, int $paymentId): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $payment = (new PaymentRepository())->find($paymentId);
        if (!$payment || (int) $payment['store_id'] !== $storeId) {
            $this->json(['error' => 'Pagamento não encontrado'], 404);
        }
        $this->json(['payment' => $payment]);
    }

    public function listPending(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $list = (new PaymentRepository())->listPendingByStore($storeId);
        $this->json(['payments' => $list]);
    }

    /**
     * Confirmação de pagamento.
     * Gerente: qualquer confirmação (ex.: PIX pendente no dashboard).
     * Funcionário: só pode confirmar pagamento em dinheiro de pedido PDV (finalizar venda no caixa).
     */
    public function confirm(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $paymentId = (int) ($input['payment_id'] ?? 0);
        if (!is_gerente_store($storeId)) {
            $payment = (new PaymentRepository())->find($paymentId);
            if (!$payment || (int) $payment['store_id'] !== $storeId) {
                $this->json(['error' => 'Pagamento não encontrado'], 404);
            }
            $method = strtolower((string) ($payment['method'] ?? ''));
            if ($method !== 'dinheiro') {
                $this->json(['error' => 'Apenas o gerente pode confirmar este pagamento.'], 403);
            }
            $order = (new OrderRepository())->find((int) $payment['order_id']);
            if (!$order || strtolower((string) ($order['order_type'] ?? '')) !== 'pdv') {
                $this->json(['error' => 'Apenas o gerente pode confirmar pagamentos fora do PDV.'], 403);
            }
        }
        try {
            $payment = $this->orderService()->confirmPayment($paymentId, $storeId);
            $this->json(['success' => true, 'payment' => $payment]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
