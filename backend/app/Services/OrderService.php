<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;

class OrderService
{
    public function __construct(
        private OrderRepository $orderRepo,
        private OrderItemRepository $orderItemRepo,
        private ProductRepository $productRepo,
        private PaymentRepository $paymentRepo,
        private StockMovementRepository $stockMovementRepo,
        private CashRegisterRepository $cashRegisterRepo,
        private CashMovementRepository $cashMovementRepo
    ) {}

    public function createOrder(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy = null, ?string $deliveryType = null, ?int $addressId = null): array
    {
        $total = 0;
        foreach ($items as $item) {
            $product = $this->productRepo->findByIdAndStore($item['product_id'], $storeId);
            if (!$product) throw new \InvalidArgumentException('Produto inválido: ' . $item['product_id']);
            $qty = (int) $item['quantity'];
            if ($qty <= 0) throw new \InvalidArgumentException('Quantidade inválida');
            $stock = (int) $product['stock_quantity'];
            if ($stock < $qty) throw new \InvalidArgumentException("Estoque insuficiente para: {$product['name']}");
            $total += $product['sale_price'] * $qty;
        }
        $orderId = $this->orderRepo->create([
            'store_id'      => $storeId,
            'customer_id'   => $customerId,
            'created_by'    => $createdBy,
            'order_type'   => $orderType,
            'delivery_type' => $deliveryType ?? 'retirada',
            'address_id'   => $addressId,
            'status'       => 'pendente',
            'total'        => $total,
        ]);
        foreach ($items as $item) {
            $product = $this->productRepo->findByIdAndStore($item['product_id'], $storeId);
            $qty = (int) $item['quantity'];
            $this->orderItemRepo->create([
                'order_id'   => $orderId,
                'product_id' => $product['id'],
                'quantity'   => $qty,
                'price'      => $product['sale_price'],
            ]);
        }
        return $this->orderRepo->find($orderId);
    }

    public function addPayment(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr = null): array
    {
        $order = $this->orderRepo->findByIdAndStore($orderId, $storeId);
        if (!$order) throw new \InvalidArgumentException('Pedido não encontrado');
        if ($order['status'] !== 'pendente') throw new \InvalidArgumentException('Pedido já pago ou cancelado');
        $paymentId = $this->paymentRepo->create([
            'order_id'    => $orderId,
            'store_id'    => $storeId,
            'method'      => $method,
            'status'      => 'pendente',
            'amount'      => $amount,
            'pix_qr_code' => $pixQr,
        ]);
        return $this->paymentRepo->find($paymentId);
    }

    public function confirmPayment(int $paymentId, int $storeId): array
    {
        $payment = $this->paymentRepo->find($paymentId);
        if (!$payment || (int) $payment['store_id'] !== $storeId) {
            throw new \InvalidArgumentException('Pagamento não encontrado');
        }
        if ($payment['status'] === 'confirmado') {
            throw new \InvalidArgumentException('Pagamento já foi confirmado');
        }
        $this->paymentRepo->updateStatus($paymentId, 'confirmado');
        $order = $this->orderRepo->find($payment['order_id']);
        $this->orderRepo->updateStatus($order['id'], 'pago');
        $items = $this->orderItemRepo->getByOrder($order['id']);
        foreach ($items as $item) {
            $product = $this->productRepo->find($item['product_id']);
            $newStock = (int) $product['stock_quantity'] - (int) $item['quantity'];
            $this->productRepo->updateStock($product['id'], $newStock);
            $this->stockMovementRepo->create([
                'store_id'   => $storeId,
                'product_id' => $product['id'],
                'user_id'    => null,
                'type'       => 'saida',
                'quantity'   => (int) $item['quantity'],
                'reason'     => 'Venda - Pedido #' . $order['id'],
            ]);
        }
        $cash = $this->cashRegisterRepo->findOpenByStore($storeId);
        $isPdv = isset($order['order_type']) && strtolower((string) $order['order_type']) === 'pdv';
        $isDinheiro = isset($payment['method']) && strtolower((string) $payment['method']) === 'dinheiro';
        if ($cash && $isPdv && $isDinheiro) {
            $this->cashMovementRepo->create([
                'cash_register_id' => $cash['id'],
                'order_id'         => $order['id'],
                'type'             => 'entrada',
                'amount'           => $payment['amount'],
                'description'      => 'Venda PDV #' . $order['id'],
            ]);
        }
        return $this->paymentRepo->find($paymentId);
    }

    public function getOrderWithItems(int $orderId, int $storeId): ?array
    {
        $order = $this->orderRepo->findByIdAndStore($orderId, $storeId);
        if (!$order) return null;
        $order['items'] = $this->orderItemRepo->getByOrder($orderId);
        $order['payments'] = $this->paymentRepo->findByOrder($orderId);
        return $order;
    }
}
