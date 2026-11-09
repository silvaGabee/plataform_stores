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

    /** GET /api/loja/{slug}/analyzing-bi/indicadores — capital, Curva ABC, giro e cupons. */
    public function indicadores(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $this->json((new AnalyzingBIService())->buildIndicators($storeId));
    }

    /**
     * GET /api/loja/{slug}/analyzing-bi/simulador-compra
     *     ?produto=&quantidade=&tma=&estoque=1&taxa=&parcelas=
     *
     * Só calcula, não grava nada. Sem produto, devolve a lista para o select.
     */
    public function simuladorCompra(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $num = static function (string $v): float {
            $v = trim($v);
            if (str_contains($v, ',')) {
                $v = str_replace(['.', ','], ['', '.'], $v);
            }

            return is_numeric($v) ? (float) $v : 0.0;
        };
        try {
            $this->json((new AnalyzingBIService())->simulateStockPurchase(
                $storeId,
                (int) ($_GET['produto'] ?? 0),
                min(100000.0, $num((string) ($_GET['quantidade'] ?? '0'))),
                $num((string) ($_GET['tma'] ?? '0')),
                ($_GET['estoque'] ?? '1') !== '0',
                $num((string) ($_GET['taxa'] ?? '0')),
                (int) ($_GET['parcelas'] ?? 0)
            ));
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
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
