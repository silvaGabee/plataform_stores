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
        $movements = $repo->listByProduct($productId, $storeId, 100);
        $this->json(['movements' => $movements]);
    }

    /**
     * GET (gerente): histórico filtrado e paginado.
     * ?product_id=&type=&user_id=&from=YYYY-MM-DD&to=YYYY-MM-DD&q=&page=
     * Com ?formato=csv devolve planilha.
     */
    public function search(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $f = [
            'product_id' => (int) ($_GET['product_id'] ?? 0),
            'type' => (string) ($_GET['type'] ?? ''),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
        ];
        $repo = new StockMovementRepository();
        if (($_GET['formato'] ?? '') === 'csv') {
            $r = $repo->search($storeId, $f, 1, 200);
            $linhas = $r['rows'];
            for ($p = 2; count($linhas) < $r['total'] && $p <= 100; $p++) {
                $linhas = array_merge($linhas, $repo->search($storeId, $f, $p, 200)['rows']);
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="historico-estoque.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Produto', 'Tipo', 'Quantidade', 'Motivo', 'Responsável'], ';');
            foreach ($linhas as $l) {
                fputcsv($out, [$l['created_at'], $l['product_name'], $l['type'], $l['quantity'], $l['reason'], $l['user_name'] ?? 'Sistema'], ';');
            }
            fclose($out);
            exit;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $r = $repo->search($storeId, $f, $page, 50);
        $this->json($r + ['page' => $page, 'per_page' => 50, 'users' => $repo->usersOfStore($storeId)]);
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
