<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\OrderService;
use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;
use App\Repositories\StoreMemberRepository;
use App\Repositories\UserAddressRepository;
use App\Repositories\DiscountRequestRepository;

class OrderApiController extends Controller
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

    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $status = $_GET['status'] ?? null;
        $type = $_GET['order_type'] ?? null;
        $repo = new OrderRepository();
        $orders = $repo->listByStore($storeId, $status, $type, 100);
        $this->json(['orders' => $orders]);
    }

    public function get(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $order = $this->orderService()->getOrderWithItems($id, $storeId);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        $this->json($order);
    }

    /** Lista pedidos pagos para a aba Entregas (Kanban). */
    public function listForEntregas(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new OrderRepository();
        $orders = $repo->listPaidForEntregas($storeId);
        $this->json(['orders' => $orders]);
    }

    /** Atualiza estágio de entrega (drag no Kanban). */
    public function updateDeliveryStage(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $stage = isset($input['stage']) ? strtolower(trim($input['stage'])) : '';
        $trackingCode = isset($input['tracking_code']) ? trim($input['tracking_code']) : null;
        $allowed = ['solicitado', 'empacotando', 'entregue_transportadora', 'em_rota', 'entregue'];
        if (!in_array($stage, $allowed, true)) {
            $this->json(['error' => 'Estágio inválido'], 400);
            return;
        }
        $repo = new OrderRepository();
        $order = $repo->findByIdAndStore($id, $storeId);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        // Só pedido PAGO anda no quadro. Antes um pedido pendente podia ser
        // marcado como Entregue — na retirada, o cliente levava sem pagar.
        if (($order['status'] ?? '') !== 'pago') {
            $this->json(['error' => 'Este pedido ainda não foi pago. Confirme o pagamento antes de movê-lo.'], 400);
            return;
        }
        $deliveryType = strtolower((string) ($order['delivery_type'] ?? 'retirada'));
        if ($deliveryType === 'retirada' && !in_array($stage, ['solicitado', 'entregue'], true)) {
            $this->json(['error' => 'Pedido de retirada só pode ser marcado como Entregue.'], 400);
            return;
        }
        // Só para a frente: Entregue não volta para Solicitado, nem Em rota
        // volta para Empacotando. Mesma etapa é aceita (reenvio sem efeito).
        $ordem = array_flip($allowed);
        $atual = (string) ($order['delivery_stage'] ?? 'solicitado');
        if (isset($ordem[$atual]) && $ordem[$stage] < $ordem[$atual]) {
            $this->json(['error' => 'A entrega não pode voltar para uma etapa anterior.'], 400);
            return;
        }
        if ($stage === 'em_rota' && (empty($trackingCode) || trim($trackingCode) === '')) {
            $this->json(['error' => 'Informe o código de rastreio para Pedido em Rota.'], 400);
            return;
        }
        $repo->updateDeliveryStage($id, $storeId, $stage, $trackingCode);
        $this->json(['success' => true, 'order' => $repo->findByIdAndStoreWithCustomer($id, $storeId)]);
    }

    /**
     * GET: pedidos online aguardando pagamento (PIX gerado ou cartão na
     * retirada). É onde o gerente confirma o PIX que caiu na conta — antes não
     * havia tela para isso, e pedido PIX da vitrine nunca virava "pago".
     */
    public function awaitingPayment(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->orderService()->expirarPendentes($storeId);
        $this->json(['orders' => (new OrderRepository())->listAwaitingPayment($storeId), 'now' => date('Y-m-d H:i:s')]);
    }

    /** POST: o gerente cancela um pedido ainda não pago. */
    public function cancel(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        try {
            $order = $this->orderService()->cancelOrder($id, $storeId, (int) ($_SESSION['logged_user_id'] ?? 0) ?: null);
            $this->json(['success' => true, 'order' => $order]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** GET: vendas pagas e estornadas, para a tela de Vendas do gerente. */
    public function sales(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->json(['orders' => (new OrderRepository())->listSales($storeId, $_GET['q'] ?? null, $_GET['status'] ?? null)]);
    }

    /** POST: o gerente estorna um pedido pago. Corpo: { reason, return_to_stock }. */
    public function refund(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        try {
            $r = $this->orderService()->refundOrder(
                $id,
                $storeId,
                (int) ($_SESSION['logged_user_id'] ?? 0),
                (string) ($input['reason'] ?? ''),
                !empty($input['return_to_stock'])
            );
            $this->json(['success' => true] + $r);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST: o próprio cliente desiste de um pedido que ainda não pagou. */
    public function cancelMine(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $order = (new OrderRepository())->findByIdAndStore($id, $storeId);
        if (!$order || !$this->userOwnsOrder($order)) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        try {
            $order = $this->orderService()->cancelOrder($id, $storeId, (int) ($_SESSION['logged_user_id'] ?? 0) ?: null);
            $this->json(['success' => true, 'order' => $order]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET: situação do pagamento de um pedido do cliente, com o PIX
     * (copia-e-cola e QR) para retomar o pagamento depois de sair da página.
     */
    public function paymentOfMine(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->orderService()->expirarPendentes($storeId);
        $order = (new OrderRepository())->findByIdAndStore($id, $storeId);
        if (!$order || !$this->userOwnsOrder($order)) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        $this->json(order_payment_view($order, $storeId));
    }

    public function create(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $orderType = $input['order_type'] ?? 'online';
        $pdv = [];
        $online = [];
        if ($orderType === 'pdv') {
            $this->requireStorePanelAccess($storeId);
            // Cliente de balcão não tem conta: a venda fica no nome do próprio
            // operador e o nome digitado vai para pdv_customer_name.
            //
            // Antes a tela buscava GET /users para pegar "o primeiro usuário"
            // da loja como cliente — rota exclusiva do gerente, então o
            // funcionário simplesmente não conseguia finalizar venda.
            $customerId = (int) ($_SESSION['user_id'] ?? $_SESSION['logged_user_id'] ?? 0);
            $pdv = [
                'discount' => (float) ($input['discount'] ?? 0),
                'customer_name' => isset($input['customer_name']) ? (string) $input['customer_name'] : null,
            ];
            // Desconto dado por funcionário precisa de aprovação do gerente.
            if ($pdv['discount'] > 0 && !is_gerente_store($storeId)) {
                $pdv['require_approval'] = true;
                $reqId = (int) ($input['discount_request_id'] ?? 0);
                $req = $reqId > 0 ? (new DiscountRequestRepository())->find($reqId, $storeId) : null;
                if ($req && (int) $req['requested_by'] !== $customerId) {
                    $req = null;
                }
                $pdv['discount_request'] = $req;
            }
        } else {
            // Compra pela vitrine: o cliente é sempre quem está logado.
            //
            // Antes, customer_id e customer_email vinham do corpo da requisição
            // — dava para lançar pedido no nome de qualquer pessoa, e um e-mail
            // desconhecido criava uma conta silenciosamente. Os dois campos
            // agora são ignorados.
            $me = $this->requireLogin();
            $customerId = (int) $me['id'];
            $cupom = trim((string) ($input['coupon_code'] ?? ''));
            $online = ['coupon_code' => $cupom !== '' ? $cupom : null];
        }
        $items = $input['items'] ?? [];
        $createdBy = $this->resolveOrderCreatedBy($storeId, $orderType);
        $deliveryType = isset($input['delivery_type']) ? strtolower(trim($input['delivery_type'])) : 'retirada';
        if (!in_array($deliveryType, ['retirada', 'entrega'], true)) {
            $deliveryType = 'retirada';
        }
        $addressId = isset($input['address_id']) ? (int) $input['address_id'] : null;
        if ($deliveryType === 'entrega') {
            if (!$addressId) {
                $this->json(['error' => 'Para entrega, selecione ou cadastre um endereço.'], 400);
                return;
            }
            $addrRepo = new UserAddressRepository();
            if (!$addrRepo->belongsToUser($addressId, $customerId)) {
                $this->json(['error' => 'Endereço inválido para este cliente.'], 400);
                return;
            }
        } else {
            $addressId = null;
        }
        if (empty($items)) {
            $this->json(['error' => 'Adicione itens ao pedido'], 400);
            return;
        }
        if (!$customerId) {
            $this->json(['error' => 'Informe o cliente do pedido.'], 400);
            return;
        }
        try {
            $order = $this->orderService()->createOrder($storeId, $customerId, $items, $orderType, $createdBy ? (int) $createdBy : null, $deliveryType, $addressId, $pdv, $online);
            $this->json(['success' => true, 'order' => $order]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Quem registrou a venda (metas / desempenho por funcionário).
     *
     * Só o PDV tem operador. Na vitrine o cliente é sempre quem está logado, então
     * um pedido online de alguém da equipe é uma compra dele, e não uma venda: antes
     * ela contava para a meta. Atender um cliente é registrar a venda no PDV.
     */
    private function resolveOrderCreatedBy(int $storeId, string $orderType): ?int
    {
        if ($orderType !== 'pdv') {
            return null;
        }
        $opId = (int) ($_SESSION['user_id'] ?? $_SESSION['logged_user_id'] ?? 0);
        if ($opId < 1) {
            return null;
        }
        // O cargo vem de store_members. Antes era lido de users.user_type e
        // users.store_id, colunas removidas na 0017 — a checagem falhava sempre
        // e nenhuma venda era creditada ao funcionário que a fez.
        $type = (new StoreMemberRepository())->role($opId, $storeId);
        if (!in_array($type, ['funcionario', 'gerente'], true)) {
            return null;
        }
        return $opId;
    }
}
