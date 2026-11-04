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
use App\Repositories\CouponRepository;
use App\Repositories\DiscountRequestRepository;

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
     * O estoque disponível já desconta as reservas (pedidos online pendentes
     * dentro do prazo). O pedido online criado aqui passa a reservar as peças
     * por PRAZO_PEDIDO; a baixa física continua em confirmPayment().
     */
    /** Prazo para escolher a forma de pagamento depois de criar o pedido. */
    public const PRAZO_PEDIDO = '30 MINUTE';
    /** Prazo para pagar o PIX gerado. */
    public const PRAZO_PIX = '30 MINUTE';
    /** Cartão na retirada: a peça fica separada esse tempo na loja. */
    public const PRAZO_RETIRADA = '3 DAY';
    /** Venda de balcão que não foi paga (operador desistiu). Não reserva. */
    public const PRAZO_PDV = '12 HOUR';

    /**
     * @param array{discount?: float, customer_name?: ?string, discount_request?: ?array} $pdv
     *        Só para venda de balcão: desconto em reais, nome do cliente e, se
     *        o operador não for gerente, a solicitação de desconto aprovada.
     * @param array{coupon_code?: ?string} $online
     *        Só para a vitrine: cupom de desconto.
     */
    public function createOrder(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy = null, ?string $deliveryType = null, ?int $addressId = null, array $pdv = [], array $online = []): array
    {
        $this->expirarPendentes($storeId);
        $order = \App\Database\Database::transaction(
            fn (): array => $this->createOrderInTransaction(
                $storeId,
                $customerId,
                $items,
                $orderType,
                $createdBy,
                $deliveryType,
                $addressId,
                $orderType === 'pdv' ? $pdv : [],
                $orderType === 'pdv' ? [] : $online
            )
        );
        StockReservation::forget($storeId);

        return $order;
    }

    /**
     * Cancela pedidos cujo prazo de pagamento acabou e devolve o uso dos
     * cupons. Roda antes de qualquer operação que dependa do estoque livre.
     */
    public function expirarPendentes(int $storeId): int
    {
        $cupons = $this->orderRepo->expirePending($storeId);
        if ($cupons !== []) {
            $repo = new CouponRepository();
            foreach ($cupons as $c) {
                $repo->release($storeId, $c);
            }
        }
        StockReservation::forget($storeId);

        return count($cupons);
    }

    /** Cancela um pedido ainda não pago (cliente ou gerente). */
    public function cancelOrder(int $orderId, int $storeId, ?int $byUserId): array
    {
        $order = $this->orderRepo->findByIdAndStore($orderId, $storeId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado.');
        }
        if ($order['status'] !== 'pendente') {
            throw new \InvalidArgumentException($order['status'] === 'cancelado'
                ? 'Este pedido já está cancelado.'
                : 'Pedido já pago não pode ser cancelado por aqui.');
        }
        if (!$this->orderRepo->cancelPending($orderId, $storeId, $byUserId)) {
            throw new \InvalidArgumentException('Não foi possível cancelar: o pedido mudou de situação.');
        }
        if (!empty($order['coupon_code'])) {
            (new CouponRepository())->release($storeId, (string) $order['coupon_code']);
        }
        StockReservation::forget($storeId);

        return $this->orderRepo->findByIdAndStore($orderId, $storeId) ?? $order;
    }

    private function createOrderInTransaction(int $storeId, int $customerId, array $items, string $orderType, ?int $createdBy, ?string $deliveryType, ?int $addressId, array $pdv, array $online = []): array
    {
        $total = 0;
        if (count($items) > 50) {
            throw new \InvalidArgumentException('Pedido com itens demais.');
        }
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
            $stock = StockReservation::available($product, $variantKey);
            if ($stock < $qty) {
                $label = $variantKey !== null ? ' (' . product_variant_key_label($product, $variantKey) . ')' : '';
                throw new \InvalidArgumentException(
                    $stock > 0
                        ? "Só restam {$stock} unidade(s) de {$product['name']}{$label}."
                        : "{$product['name']}{$label} esgotou."
                );
            }
            $total += product_effective_price($product) * $qty;
        }
        $total = round($total, 2);

        // O desconto é validado aqui, contra o subtotal calculado no servidor
        // com os preços do banco — nunca contra um total vindo do navegador.
        $discount = round((float) ($pdv['discount'] ?? 0), 2);
        if ($discount < 0) {
            throw new \InvalidArgumentException('Desconto inválido.');
        }
        if ($discount > $total) {
            throw new \InvalidArgumentException('O desconto não pode ser maior que o valor da venda.');
        }
        $pedidoDesconto = $pdv['discount_request'] ?? null;
        if ($discount > 0 && !empty($pdv['require_approval'])) {
            // Funcionário só dá desconto com aprovação do gerente, e a aprovação
            // vale para exatamente este valor sobre exatamente este subtotal.
            if (!$pedidoDesconto || ($pedidoDesconto['status'] ?? '') !== 'aprovado') {
                throw new \InvalidArgumentException('Desconto precisa da aprovação de um gerente.');
            }
            if (abs((float) $pedidoDesconto['discount'] - $discount) > 0.009 || abs((float) $pedidoDesconto['subtotal'] - $total) > 0.009) {
                throw new \InvalidArgumentException('O carrinho mudou depois da aprovação. Solicite o desconto de novo.');
            }
        }
        $couponCode = null;
        if ($orderType !== 'pdv' && !empty($online['coupon_code'])) {
            $couponRepo = new CouponRepository();
            $avaliado = $couponRepo->evaluate($storeId, (string) $online['coupon_code'], $total);
            if (!$couponRepo->consume((int) $avaliado['coupon']['id'])) {
                throw new \InvalidArgumentException('Este cupom atingiu o limite de usos.');
            }
            $discount = $avaliado['discount'];
            $couponCode = (string) $avaliado['coupon']['code'];
        }
        $customerName = trim((string) ($pdv['customer_name'] ?? ''));
        $customerName = $customerName !== '' ? mb_substr($customerName, 0, 120) : null;

        $orderId = $this->orderRepo->create([
            'store_id'      => $storeId,
            'customer_id'   => $customerId,
            'created_by'    => $createdBy,
            'order_type'   => $orderType,
            'delivery_type' => $deliveryType ?? 'retirada',
            'address_id'   => $addressId,
            'status'       => 'pendente',
            'total'        => round($total - $discount, 2),
            'discount'     => $discount,
            'pdv_customer_name' => $customerName,
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
                'price'        => product_effective_price($product),
                'cost_price'   => $product['cost_price'] ?? null,
            ]);
        }
        if ($couponCode !== null) {
            $this->orderRepo->setCoupon($orderId, $couponCode);
        }
        if ($pedidoDesconto && $discount > 0 && !empty($pdv['require_approval'])) {
            if (!(new DiscountRequestRepository())->markUsed((int) $pedidoDesconto['id'], $storeId, $orderId)) {
                throw new \InvalidArgumentException('Esta aprovação de desconto já foi usada.');
            }
        }
        $this->orderRepo->setExpiresAt($orderId, $orderType === 'pdv' ? self::PRAZO_PDV : self::PRAZO_PEDIDO);

        return $this->orderRepo->find($orderId);
    }

    /**
     * Cria o registro de pagamento de um pedido pendente.
     *
     * Em transação com o pedido travado: dois cliques no botão de finalizar
     * criavam dois pagamentos para o mesmo pedido, porque ambos liam
     * status = 'pendente' antes de qualquer um gravar.
     */
    /**
     * @param array{installments?:int,installment_amount?:?float,interest_amount?:float} $parcelamento
     */
    public function addPayment(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr = null, ?array $cardMeta = null, array $parcelamento = []): array
    {
        $this->expirarPendentes($storeId);

        return \App\Database\Database::transaction(
            fn (): array => $this->addPaymentInTransaction($orderId, $storeId, $method, $amount, $pixQr, $cardMeta, $parcelamento)
        );
    }

    private function addPaymentInTransaction(int $orderId, int $storeId, string $method, float $amount, ?string $pixQr, ?array $cardMeta, array $parcelamento = []): array
    {
        $order = $this->orderRepo->findByIdAndStoreForUpdate($orderId, $storeId);
        if (!$order) throw new \InvalidArgumentException('Pedido não encontrado');
        if ($order['status'] === 'cancelado') throw new \InvalidArgumentException('Este pedido foi cancelado ou expirou. Faça um novo pedido.');
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
            'installments' => (int) ($parcelamento['installments'] ?? 1),
            'installment_amount' => $parcelamento['installment_amount'] ?? null,
            'interest_amount' => (float) ($parcelamento['interest_amount'] ?? 0),
            'pix_qr_code' => $pixQr,
        ];
        if ($cardMeta) {
            $row['card_holder'] = $cardMeta['holder'] ?? null;
            $row['card_last4'] = $cardMeta['last4'] ?? null;
            $row['card_brand'] = $cardMeta['brand'] ?? null;
        }
        $paymentId = $this->paymentRepo->create($row);
        // Novo prazo conforme a forma de pagamento (pedido online).
        if (strtolower((string) ($order['order_type'] ?? 'online')) === 'online') {
            $retirada = strtolower((string) ($order['delivery_type'] ?? 'retirada')) === 'retirada';
            $this->orderRepo->setExpiresAt($orderId, $method === 'cartao' && $retirada ? self::PRAZO_RETIRADA : self::PRAZO_PIX);
        }
        StockReservation::forget($storeId);

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
        $this->expirarPendentes($storeId);
        $r = \App\Database\Database::transaction(
            fn (): array => $this->confirmPaymentInTransaction($paymentId, $storeId)
        );
        StockReservation::forget($storeId);

        return $r;
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
            throw new \InvalidArgumentException('Pagamento cancelado (o pedido foi cancelado ou o prazo expirou) e não pode ser confirmado.');
        }
        $order = $this->orderRepo->find((int) $payment['order_id']);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido do pagamento não encontrado');
        }
        $this->paymentRepo->updateStatus($paymentId, 'confirmado');
        $this->orderRepo->updateStatus((int) $order['id'], 'pago');
        $this->orderRepo->setExpiresAt((int) $order['id'], null);
        if (strtolower((string) ($order['order_type'] ?? '')) === 'pdv') {
            // Balcão: o cliente sai com a mercadoria na hora. Sem isto as vendas
            // do PDV caíam no quadro de Entregas como "Solicitado".
            $this->orderRepo->updateDeliveryStage((int) $order['id'], $storeId, 'entregue');
        }
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
