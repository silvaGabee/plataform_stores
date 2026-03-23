<?php

namespace App\Services;

use App\Repositories\CashRegisterRepository;
use App\Repositories\CashMovementRepository;

class CashRegisterService
{
    public function __construct(
        private CashRegisterRepository $cashRepo,
        private CashMovementRepository $movementRepo
    ) {}

    public function getOpenRegister(int $storeId): ?array
    {
        return $this->cashRepo->findOpenByStore($storeId);
    }

    public function open(int $storeId, int $userId, float $initialAmount = 0): array
    {
        if ($this->cashRepo->findOpenByStore($storeId)) {
            throw new \RuntimeException('Já existe um caixa aberto para esta loja.');
        }
        $id = $this->cashRepo->open([
            'store_id'       => $storeId,
            'opened_by'      => $userId,
            'initial_amount' => $initialAmount,
        ]);
        return $this->cashRepo->find($id);
    }

    public function close(int $cashRegisterId, int $storeId, float $finalAmount): array
    {
        $cash = $this->cashRepo->find($cashRegisterId);
        if (!$cash || (int) $cash['store_id'] !== $storeId) {
            throw new \InvalidArgumentException('Caixa não encontrado');
        }
        if ($cash['closed_at'] !== null) {
            throw new \RuntimeException('Este caixa já foi fechado.');
        }
        $this->cashRepo->close($cashRegisterId, $finalAmount);
        return $this->cashRepo->find($cashRegisterId);
    }

    public function addMovement(int $cashRegisterId, string $type, float $amount, ?string $description = null, ?int $orderId = null): void
    {
        $this->movementRepo->create([
            'cash_register_id' => $cashRegisterId,
            'order_id'         => $orderId,
            'type'             => $type,
            'amount'           => $amount,
            'description'      => $description,
        ]);
    }

    public function getMovements(int $cashRegisterId): array
    {
        return $this->movementRepo->listByCashRegister($cashRegisterId);
    }

    public function getCurrentBalance(int $cashRegisterId): float
    {
        $cash = $this->cashRepo->find($cashRegisterId);
        if (!$cash) return 0;
        $movements = $this->movementRepo->listByCashRegister($cashRegisterId);
        $balance = (float) $cash['initial_amount'];
        foreach ($movements as $m) {
            $type = isset($m['type']) ? strtolower((string) $m['type']) : '';
            $amt = isset($m['amount']) ? (float) $m['amount'] : 0;
            if ($type === 'entrada') {
                $balance += $amt;
            } else {
                $balance -= $amt;
            }
        }
        return $balance;
    }
}
