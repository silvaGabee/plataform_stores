<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVariantRepository;
use App\Repositories\ProductImageRepository;
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
        private CashMovementRepository $cashMovementRepo,
        private ?ProductVariantRepository $variantRepo = null
    ) {
        $this->variantRepo = $variantRepo ?? new ProductVariantRepository();
    }

    private ?ProductService $productService = null;

    private function productService(): ProductService
    {
        if ($this->productService === null) {
            $this->productService = new ProductService(
                $this->productRepo,
                $this->stockMovementRepo,
                new ProductImageRepository(),
                $this->variantRepo
            );
        }

        return $this->productService;
    }

    public function createOrder(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy = null, ?string $deliveryType = null, ?int $addressId = null): array
    {
        $total = 0;
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $this->productService()->getByIdAndStore($productId, $storeId);
            if (!$product) {
                throw new \InvalidArgumentException('Produto inválido: ' . $productId);
            }
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException('Quantidade inválida');
            }
            $variantKey = isset($item['variant_key']) ? trim((string) $item['variant_key']) : null;
            if ($variantKey === '') {
                $variantKey = null;
            }
            if (product_has_variants($product) && $variantKey === null) {
                throw new \InvalidArgumentException('Selecione cor e tamanho para: ' . ($product['name'] ?? ''));
            }
            $stock = product_sale_available_stock($product, $variantKey);
            if ($stock < $qty) {
                throw new \InvalidArgumentException("Estoque insuficiente para: {$product['name']}");
            }
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
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $this->productService()->getByIdAndStore($productId, $storeId);
            if (!$product) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 0);
            $variantKey = isset($item['variant_key']) ? trim((string) $item['variant_key']) : null;
            if ($variantKey === '') {
                $variantKey = null;
            }
            $this->orderItemRepo->create([
                'order_id'     => $orderId,
                'product_id'   => $product['id'],
                'variant_key'  => $variantKey,
                'quantity'     => $qty,
                'price'        => $product['sale_price'],
            ]);
        }
        return $this->orderRepo->find($orderId);
    }

    public function addPayment(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr = null, ?array $cardMeta = null): array
    {
        $order = $this->orderRepo->findByIdAndStore($orderId, $storeId);
        if (!$order) throw new \InvalidArgumentException('Pedido não encontrado');
        if ($order['status'] !== 'pendente') throw new \InvalidArgumentException('Pedido já pago ou cancelado');
        $row = [
            'order_id'    => $orderId,
            'store_id'    => $storeId,
            'method'      => $method,
            'status'      => 'pendente',
            'amount'      => $amount,
            'pix_qr_code' => $pixQr,
        ];
        if ($cardMeta) {
            $row['card_holder'] = $cardMeta['holder'] ?? null;
            $row['card_last4'] = $cardMeta['last4'] ?? null;
            $row['card_brand'] = $cardMeta['brand'] ?? null;
        }
        $paymentId = $this->paymentRepo->create($row);
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
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $this->productService()->getByIdAndStore($productId, $storeId);
            if (!$product) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 0);
            $variantKey = isset($item['variant_key']) ? trim((string) $item['variant_key']) : null;
            if ($variantKey === '') {
                $variantKey = null;
            }
            product_apply_sale_stock_decrement(
                $product,
                $qty,
                $variantKey,
                $this->variantRepo,
                $this->productRepo
            );
            $reason = 'Venda - Pedido #' . $order['id'];
            if ($variantKey !== null) {
                $label = product_variant_key_label($product, $variantKey);
                if ($label !== '') {
                    $reason .= ' (' . $label . ')';
                }
            }
            $this->stockMovementRepo->create([
                'store_id'   => $storeId,
                'product_id' => $product['id'],
                'user_id'    => null,
                'type'       => 'saida',
                'quantity'   => $qty,
                'reason'     => $reason,
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
