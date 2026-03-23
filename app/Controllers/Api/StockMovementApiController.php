<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\StockMovementRepository;

class StockMovementApiController extends Controller
{
    public function listByProduct(string $slug, int $productId): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new StockMovementRepository();
        $movements = $repo->listByProduct($productId, 100);
        $this->json(['movements' => $movements]);
    }

    public function listByStore(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new StockMovementRepository();
        $movements = $repo->listByStore($storeId, 100);
        $this->json(['movements' => $movements]);
    }
}
