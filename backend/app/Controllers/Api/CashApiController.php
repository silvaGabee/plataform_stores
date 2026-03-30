<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\CashRegisterService;
use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;

class CashApiController extends Controller
{
    private function service(): CashRegisterService
    {
        return new CashRegisterService(new CashRegisterRepository(), new CashMovementRepository());
    }

    public function status(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $open = $this->service()->getOpenRegister($storeId);
        $balance = $open ? $this->service()->getCurrentBalance($open['id']) : null;
        $this->json(['open' => $open, 'balance' => $balance]);
    }

    public function open(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->json(['error' => 'Não autorizado'], 401);
        }
        $input = $this->getJsonInput();
        $initial = (float) ($input['initial_amount'] ?? 0);
        try {
            $cash = $this->service()->open($storeId, (int) $userId, $initial);
            $this->json(['success' => true, 'cash_register' => $cash]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function close(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $cashRegisterId = (int) ($input['cash_register_id'] ?? 0);
        $finalAmount = (float) ($input['final_amount'] ?? 0);
        try {
            $cash = $this->service()->close($cashRegisterId, $storeId, $finalAmount);
            $this->json(['success' => true, 'cash_register' => $cash]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function movements(string $slug, int $cashRegisterId): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $cash = (new CashRegisterRepository())->find($cashRegisterId);
        if (!$cash || (int) $cash['store_id'] !== $storeId) {
            $this->json(['error' => 'Caixa não encontrado'], 404);
        }
        $movements = $this->service()->getMovements($cashRegisterId);
        $this->json(['movements' => $movements]);
    }

    public function addMovement(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $cashRegisterId = (int) ($input['cash_register_id'] ?? 0);
        $type = $input['type'] ?? 'entrada';
        $amount = (float) ($input['amount'] ?? 0);
        $description = $input['description'] ?? null;
        if (!in_array($type, ['entrada', 'saida']) || $amount <= 0) {
            $this->json(['error' => 'Dados inválidos'], 400);
        }
        $cash = (new CashRegisterRepository())->find($cashRegisterId);
        if (!$cash || (int) $cash['store_id'] !== $storeId || $cash['closed_at']) {
            $this->json(['error' => 'Caixa não disponível'], 400);
        }
        $this->service()->addMovement($cashRegisterId, $type, $amount, $description, null);
        $this->json(['success' => true, 'balance' => $this->service()->getCurrentBalance($cashRegisterId)]);
    }
}
