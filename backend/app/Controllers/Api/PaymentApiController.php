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
        } else {
            // Pedido online só podia ser pago por quem informasse o id — e ids
            // são sequenciais. Sem esta checagem dá para varrer os pedidos
            // pendentes da loja e gerar pagamento no pedido de qualquer um.
            $this->requireOrderAccess($order, $storeId);
        }
        $method = $input['method'] ?? 'pix';
        if (!in_array($method, ['pix', 'dinheiro', 'cartao'], true)) {
            $this->json(['error' => 'Método inválido'], 400);
            return;
        }
        if ($method === 'dinheiro' && $orderType !== 'pdv') {
            $this->json(['error' => 'Pagamento em dinheiro não está disponível na loja online.'], 400);
            return;
        }
        $cardMeta = null;
        $autoConfirmCard = false;
        $deliveryType = strtolower((string) ($order['delivery_type'] ?? 'retirada'));
        if ($method === 'cartao' && $orderType !== 'pdv' && $deliveryType === 'entrega') {
            try {
                $cardMeta = card_validate_checkout_input(is_array($input['card'] ?? null) ? $input['card'] : null);
            } catch (\InvalidArgumentException $e) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            if ($cardMeta === null) {
                $this->json(['error' => 'Informe os dados do cartão para concluir o pagamento.'], 400);
                return;
            }
            $autoConfirmCard = true;
        }
        // Online: o valor depende da forma de pagamento (desconto à vista no
        // PIX). O QR precisa nascer com o mesmo valor que o OrderService grava.
        $amount = $orderType === 'pdv'
            ? (float) $order['total']
            : $this->orderService()->precoParaPagamento($order, (string) $method)['total'];
        $parcelamento = [];
        if ($method === 'cartao' && $orderType !== 'pdv' && $deliveryType === 'entrega') {
            // Parcelamento real no cartão, pela regra da loja (tabela Price
            // acima das parcelas sem juros). O valor cobrado inclui os juros.
            $store = (new \App\Repositories\StoreRepository())->find($storeId) ?? [];
            $n = max(1, (int) ($input['installments'] ?? 1));
            try {
                $q = \App\Services\InstallmentPlan::quote($store, $amount, $n);
            } catch (\InvalidArgumentException $e) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $rules = \App\Services\InstallmentPlan::rules($store);
            if ($n > 1 && $q['parcela'] < $rules['min_value']) {
                $this->json(['error' => 'A parcela mínima desta loja é R$ ' . number_format($rules['min_value'], 2, ',', '.') . '.'], 400);
                return;
            }
            $parcelamento = ['installments' => $n, 'installment_amount' => $q['parcela'], 'interest_amount' => $q['juros']];
            $amount = $q['total'];
        }
        $pixQr = null;
        $pixManual = null;
        if ($method === 'pix') {
            $pixConfigRepo = new StorePixConfigRepository();
            // Uma chamada só devolve o QR e o copia e cola da mesma origem.
            // Buscá-los separadamente permitia que o cliente escaneasse um
            // payload e copiasse outro, já que cada gerador formata a
            // referência à sua maneira.
            $pix = (new PixService($pixConfigRepo))->generate($storeId, $amount, 'Pedido ' . $orderId);
            $pixQr = $pix['qr_code'];
            $config = $pixConfigRepo->findByStore($storeId);
            if (!empty($config['pix_key'])) {
                $pixManual = [
                    'chave' => $config['pix_key'],
                    'valor' => $amount,
                    'nome' => $config['merchant_name'] ?? 'Loja',
                    'copia_cola' => $pix['copia_cola'],
                ];
            }
        }
        try {
            $payment = $this->orderService()->addPayment($orderId, $storeId, $method, $amount, $pixQr, $cardMeta, $parcelamento);
            if ($autoConfirmCard) {
                $payment = $this->orderService()->confirmPayment((int) $payment['id'], $storeId);
            }
            if ($pixManual !== null) {
                $payment['pix_manual'] = $pixManual;
            }
            $orderNow = (new OrderRepository())->findByIdAndStore($orderId, $storeId);
            $payment['expires_at'] = $orderNow['expires_at'] ?? null;
            $payment['server_now'] = date('Y-m-d H:i:s');
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
        // O polling do checkout consulta este endpoint, mas ele devolve o
        // registro inteiro do pagamento (valor, método, últimos 4 dígitos do
        // cartão) — só para quem tem acesso ao pedido.
        $order = (new OrderRepository())->findByIdAndStore((int) $payment['order_id'], $storeId);
        if (!$order) {
            $this->json(['error' => 'Pagamento não encontrado'], 404);
        }
        $this->requireOrderAccess($order, $storeId);
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
     * Funcionário: só confirma pagamento de pedido PDV — é ele quem está no
     * balcão vendo a nota, a maquininha aprovar ou o PIX cair. Pagamento de
     * pedido online continua exclusivo do gerente.
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
