<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\AnalyzingBIService;

class AnalyzingBIApiController extends Controller
{
    /** GET /api/loja/{slug}/analyzing-bi — apenas gerente da loja. */
    public function index(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $service = new AnalyzingBIService();
        $this->json($service->buildPayload($storeId));
    }

    /** GET /api/loja/{slug}/analyzing-bi/faturamento?periodo=7d|30d|3m */
    public function faturamento(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $periodo = strtolower(trim((string) ($_GET['periodo'] ?? '30d')));
        $service = new AnalyzingBIService();
        $this->json($service->getFaturamentoAoLongoDoTempo($storeId, $periodo));
    }
}
