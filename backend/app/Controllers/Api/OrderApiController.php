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
use App\Repositories\UserRepository;
use App\Repositories\UserAddressRepository;

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
        $deliveryType = strtolower((string) ($order['delivery_type'] ?? 'retirada'));
        if ($deliveryType === 'retirada' && !in_array($stage, ['solicitado', 'entregue'], true)) {
            $this->json(['error' => 'Pedido de retirada só pode ser marcado como Entregue.'], 400);
            return;
        }
        if ($stage === 'em_rota' && (empty($trackingCode) || trim($trackingCode) === '')) {
            $this->json(['error' => 'Informe o código de rastreio para Pedido em Rota.'], 400);
            return;
        }
        $repo->updateDeliveryStage($id, $storeId, $stage, $trackingCode);
        $this->json(['success' => true, 'order' => $repo->findByIdAndStoreWithCustomer($id, $storeId)]);
    }

    /** Exclui pedido do histórico de entregas (ex.: após produto removido). Apenas estágio "entregue". */
    public function deleteFromEntregas(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new OrderRepository();
        $order = $repo->findByIdAndStore($id, $storeId);
        if (!$order) {
            $this->json(['error' => 'Pedido não encontrado'], 404);
            return;
        }
        $stage = strtolower((string) ($order['delivery_stage'] ?? ''));
        if ($stage !== 'entregue') {
            $this->json(['error' => 'Só é possível remover pedidos já marcados como Entregue.'], 400);
            return;
        }
        if (!$repo->deleteByIdAndStore($id, $storeId)) {
            $this->json(['error' => 'Não foi possível remover o pedido.'], 400);
            return;
        }
        $this->json(['success' => true]);
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
        if ($orderType === 'pdv') {
            $this->requireStorePanelAccess($storeId);
            // No PDV quem escolhe o cliente é o operador autenticado.
            $customerId = (int) ($input['customer_id'] ?? 0);
        } else {
            // Compra pela vitrine: o cliente é sempre quem está logado.
            //
            // Antes, customer_id e customer_email vinham do corpo da requisição
            // — dava para lançar pedido no nome de qualquer pessoa, e um e-mail
            // desconhecido criava uma conta silenciosamente. Os dois campos
            // agora são ignorados.
            $me = $this->requireLogin();
            $customerId = (int) $me['id'];
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
            $ownerIds = $orderType === 'pdv' ? [$customerId] : $this->currentUserIdentityIds();
            if (!$addrRepo->belongsToAnyUser($addressId, $ownerIds)) {
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
            $order = $this->orderService()->createOrder($storeId, $customerId, $items, $orderType, $createdBy ? (int) $createdBy : null, $deliveryType, $addressId);
            $this->json(['success' => true, 'order' => $order]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Quem registrou a venda (metas / desempenho por funcionário).
     * PDV: operador logado no painel.
     * Online: se um gerente/funcionário da mesma loja estiver logado (ex.: atendimento pelo site),
     * a venda entra na meta dele; cliente comum logado não recebe esse crédito.
     */
    private function resolveOrderCreatedBy(int $storeId, string $orderType): ?int
    {
        $opId = (int) ($_SESSION['user_id'] ?? $_SESSION['logged_user_id'] ?? 0);
        if ($opId < 1) {
            return null;
        }
        $userRepo = new UserRepository();
        $op = $userRepo->find($opId);
        if (!$op || (int) ($op['store_id'] ?? 0) !== $storeId) {
            return null;
        }
        $type = strtolower((string) ($op['user_type'] ?? ''));
        if (!in_array($type, ['funcionario', 'gerente'], true)) {
            return null;
        }
        if ($orderType === 'pdv') {
            return $opId;
        }
        if ($orderType === 'online') {
            return $opId;
        }
        return null;
    }
}
