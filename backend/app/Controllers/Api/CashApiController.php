<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\CashRegisterService;
use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;

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

    /**
     * Resumo do caixa aberto: saldo da gaveta decomposto (abertura, vendas em
     * dinheiro, suprimentos, sangrias), vendas do turno por forma de pagamento
     * e as últimas vendas. É o que o PDV mostra no topo e na conferência de
     * fechamento.
     */
    public function summary(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $open = $this->service()->getOpenRegister($storeId);
        if (!$open) {
            $this->json(['open' => null]);
        }
        $movements = $this->service()->getMovements((int) $open['id']);
        $vendasDinheiro = 0.0;
        $suprimentos = 0.0;
        $sangrias = 0.0;
        foreach ($movements as $m) {
            $amt = (float) ($m['amount'] ?? 0);
            if (strtolower((string) $m['type']) === 'saida') {
                $sangrias += $amt;
            } elseif (!empty($m['order_id'])) {
                $vendasDinheiro += $amt;
            } else {
                $suprimentos += $amt;
            }
        }
        $initial = (float) $open['initial_amount'];
        $payments = new PaymentRepository();
        $byMethod = $payments->pdvTotalsByMethodSince($storeId, (string) $open['opened_at']);
        $salesCount = 0;
        $salesTotal = 0.0;
        $discountTotal = 0.0;
        foreach ($byMethod as $row) {
            $salesCount += $row['count'];
            $salesTotal += $row['total'];
            $discountTotal += $row['discount'];
        }
        $operator = (new UserRepository())->find((int) $open['opened_by']);

        $this->json([
            'open' => $open,
            'opened_by_name' => $operator['name'] ?? null,
            'cash' => [
                'initial' => round($initial, 2),
                'sales' => round($vendasDinheiro, 2),
                'supplies' => round($suprimentos, 2),
                'withdrawals' => round($sangrias, 2),
                'expected' => round($initial + $vendasDinheiro + $suprimentos - $sangrias, 2),
            ],
            'sales' => [
                'count' => $salesCount,
                'total' => round($salesTotal, 2),
                'discount' => round($discountTotal, 2),
                'average' => $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0,
                'by_method' => $byMethod,
            ],
            'recent' => $payments->pdvRecentSalesSince($storeId, (string) $open['opened_at'], 8),
            'movements' => $movements,
        ]);
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
        $initial = round((float) ($input['initial_amount'] ?? 0), 2);
        if ($initial < 0) {
            $this->json(['error' => 'O fundo de troco não pode ser negativo.'], 400);
        }
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

    /** GET: últimos fechamentos (contado × esperado × diferença, quem abriu e fechou). */
    public function closings(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->json(['closings' => (new CashRegisterRepository())->listClosedWithPeople($storeId, 15)]);
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
        $description = trim((string) ($input['description'] ?? ''));
        $description = $description !== '' ? mb_substr($description, 0, 255) : null;
        if (!in_array($type, ['entrada', 'saida']) || $amount <= 0) {
            $this->json(['error' => 'Dados inválidos'], 400);
        }
        $cash = (new CashRegisterRepository())->find($cashRegisterId);
        if (!$cash || (int) $cash['store_id'] !== $storeId || $cash['closed_at']) {
            $this->json(['error' => 'Caixa não disponível'], 400);
        }
        // Sangria maior que o dinheiro na gaveta é erro de digitação, não saída.
        if ($type === 'saida' && $amount > $this->service()->getCurrentBalance($cashRegisterId) + 0.001) {
            $this->json(['error' => 'Valor da sangria maior que o saldo em dinheiro do caixa.'], 400);
        }
        $this->service()->addMovement($cashRegisterId, $type, $amount, $description, null);
        $this->json(['success' => true, 'balance' => $this->service()->getCurrentBalance($cashRegisterId)]);
    }
}
