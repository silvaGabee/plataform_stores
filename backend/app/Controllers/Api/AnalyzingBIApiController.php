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
        $num = static function (mixed $v): float {
            $v = input_text($v);
            if (str_contains($v, ',')) {
                $v = str_replace(['.', ','], ['', '.'], $v);
            }

            return is_numeric($v) ? (float) $v : 0.0;
        };
        // Peças são inteiras. Antes 0,0004 peça dava investimento de R$ 0,00,
        // VPL 0 e a decisão "compensa". Só vale quando há produto: sem ele, a
        // rota devolve a lista de produtos que a tela carrega ao abrir.
        $produto = is_scalar($_GET['produto'] ?? null) ? (int) $_GET['produto'] : 0;
        $quantidade = $num($_GET['quantidade'] ?? '0');
        if ($produto > 0 && ($quantidade < 1 || floor($quantidade) !== $quantidade)) {
            $this->json(['error' => 'Informe a quantidade em peças inteiras (1 ou mais).'], 400);
        }
        try {
            $this->json((new AnalyzingBIService())->simulateStockPurchase(
                $storeId,
                $produto,
                min(100000.0, $quantidade),
                $num($_GET['tma'] ?? '0'),
                ($_GET['estoque'] ?? '1') !== '0',
                $num($_GET['taxa'] ?? '0'),
                (int) ($_GET['parcelas'] ?? 0),
                $num($_GET['despesas'] ?? '0')
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
