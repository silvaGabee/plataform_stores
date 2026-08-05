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

    /**
     * Cria o pedido e seus itens.
     *
     * Tudo numa transação: antes, o pedido era inserido e os itens vinham
     * depois, num laço separado. Qualquer falha no meio deixava um pedido sem
     * itens (ou com parte deles) e um total que não batia com nada.
     *
     * A checagem de estoque aqui é só para dar erro cedo e legível ao cliente —
     * ela NÃO reserva nada. A garantia real está em confirmPayment(), onde a
     * baixa é atômica.
     */
    public function createOrder(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy = null, ?string $deliveryType = null, ?int $addressId = null): array
    {
        return \App\Database\Database::transaction(
            fn (): array => $this->createOrderInTransaction(
                $storeId,
                $customerId,
                $items,
                $orderType,
                $createdBy,
                $deliveryType,
                $addressId
            )
        );
    }

    private function createOrderInTransaction(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy, ?string $deliveryType, ?int $addressId): array
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

    /**
     * Cria o registro de pagamento de um pedido pendente.
     *
     * Em transação com o pedido travado: dois cliques no botão de finalizar
     * criavam dois pagamentos para o mesmo pedido, porque ambos liam
     * status = 'pendente' antes de qualquer um gravar.
     */
    public function addPayment(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr = null, ?array $cardMeta = null): array
    {
        return \App\Database\Database::transaction(
            fn (): array => $this->addPaymentInTransaction($orderId, $storeId, $method, $amount, $pixQr, $cardMeta)
        );
    }

    private function addPaymentInTransaction(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr, ?array $cardMeta): array
    {
        $order = $this->orderRepo->findByIdAndStoreForUpdate($orderId, $storeId);
        if (!$order) throw new \InvalidArgumentException('Pedido não encontrado');
        if ($order['status'] !== 'pendente') throw new \InvalidArgumentException('Pedido já pago ou cancelado');
        // Já existe pagamento em aberto para este pedido?
        //   mesmo método e valor -> é o mesmo pedido de novo (duplo clique,
        //     retentativa de rede). Devolve o que já existe, sem duplicar.
        //   método diferente -> a pessoa mudou de ideia. Cancela o anterior.
        // Recusar os dois casos travaria quem só quer trocar de PIX para cartão.
        $existing = $this->paymentRepo->getPendingByOrder($orderId);
        if ($existing !== null) {
            $sameMethod = strtolower((string) $existing['method']) === strtolower($method);
            if ($sameMethod && abs((float) $existing['amount'] - $amount) < 0.01) {
                return $existing;
            }
            $this->paymentRepo->updateStatus((int) $existing['id'], 'cancelado');
        }
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

    /**
     * Confirma o pagamento: marca o pedido como pago, baixa o estoque, registra
     * a movimentação e lança no caixa quando for venda em dinheiro no PDV.
     *
     * São cinco escritas em quatro tabelas. Antes rodavam soltas, uma após a
     * outra: uma falha no meio deixava pedido pago com estoque intacto, ou
     * estoque baixado sem lançamento no caixa — estados que ninguém reconcilia
     * depois. Agora ou tudo acontece, ou nada acontece.
     */
    public function confirmPayment(int $paymentId, int $storeId): array
    {
        return \App\Database\Database::transaction(
            fn (): array => $this->confirmPaymentInTransaction($paymentId, $storeId)
        );
    }

    private function confirmPaymentInTransaction(int $paymentId, int $storeId): array
    {
        // FOR UPDATE trava a linha do pagamento até o commit. É o que impede
        // duas confirmações simultâneas de passarem juntas pela checagem de
        // status e baixarem o estoque duas vezes para a mesma venda.
        $payment = $this->paymentRepo->findForUpdate($paymentId);
        if (!$payment || (int) $payment['store_id'] !== $storeId) {
            throw new \InvalidArgumentException('Pagamento não encontrado');
        }
        if ($payment['status'] === 'confirmado') {
            throw new \InvalidArgumentException('Pagamento já foi confirmado');
        }
        if ($payment['status'] === 'cancelado') {
            throw new \InvalidArgumentException('Pagamento cancelado não pode ser confirmado');
        }
        $order = $this->orderRepo->find((int) $payment['order_id']);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido do pagamento não encontrado');
        }
        $this->paymentRepo->updateStatus($paymentId, 'confirmado');
        $this->orderRepo->updateStatus((int) $order['id'], 'pago');
        $items = $this->orderItemRepo->getByOrder((int) $order['id']);
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $this->productService()->getByIdAndStore($productId, $storeId);
            if (!$product) {
                // Antes era `continue`: o item sumia da baixa e o pedido era
                // pago mesmo assim. Um produto apagado no meio da compra é
                // motivo para recusar, não para ignorar.
                throw new \RuntimeException('Um produto do pedido não existe mais. Pagamento não confirmado.');
            }
            $qty = (int) ($item['quantity'] ?? 0);
            $variantKey = isset($item['variant_key']) ? trim((string) $item['variant_key']) : null;
            if ($variantKey === '') {
                $variantKey = null;
            }
            $label = $variantKey !== null ? product_variant_key_label($product, $variantKey) : '';

            // A baixa agora pode recusar por saldo insuficiente. Lançar aqui
            // desfaz a transação inteira: sem pedido pago sem mercadoria.
            $baixou = product_apply_sale_stock_decrement(
                $product,
                $qty,
                $variantKey,
                $this->variantRepo,
                $this->productRepo
            );
            if (!$baixou) {
                throw new \RuntimeException(
                    'Estoque insuficiente para: ' . (string) ($product['name'] ?? 'produto')
                        . ($label !== '' ? ' (' . $label . ')' : '')
                        . '. O pagamento não foi confirmado.'
                );
            }
            $reason = 'Venda - Pedido #' . $order['id'];
            if ($label !== '') {
                $reason .= ' (' . $label . ')';
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
