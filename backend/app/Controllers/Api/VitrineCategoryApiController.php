<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\StoreRepository;
use App\Repositories\StoreVitrineCategoryRepository;
use App\Services\ProductService;

class VitrineCategoryApiController extends Controller
{
    private const MAX_PER_STORE = 24;

    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }
        $this->requireStorePanelAccess($storeId);

        $repo = new StoreVitrineCategoryRepository();
        $items = array_map([$this, 'formatCategory'], $repo->listByStore($storeId));

        $icons = array_map(static function (array $icon): array {
            return [
                'key' => $icon['key'],
                'label' => $icon['label'],
                'url' => $icon['url'],
            ];
        }, vitrine_category_icon_catalog());

        $this->json([
            'categories' => $items,
            'icons' => $icons,
        ]);
    }

    /** Categorias públicas para a vitrine (sem auth de painel). */
    public function listPublic(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }

        $repo = new StoreVitrineCategoryRepository();
        $items = array_map([$this, 'formatCategory'], $repo->listByStore($storeId));

        $this->json(['categories' => $items]);
    }

    /** HTML do catálogo inicial (troca de categoria sem recarregar a página). */
    public function homeContentPublic(string $slug): void
    {
        $store = $this->getStoreRowBySlug($slug);
        if (!$store) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $products = $service->listForStore((int) $store['id'], true);
        $this->json([
            'html' => $this->renderView('store/_dynamic_home', [
                'store' => $store,
                'products' => $products,
            ]),
            'title' => (string) $store['name'],
            'category_id' => null,
            'mode' => 'home',
        ]);
    }

    /** HTML dos produtos de uma categoria (troca sem recarregar). */
    public function contentPublic(string $slug, int $id): void
    {
        $store = $this->getStoreRowBySlug($slug);
        if (!$store) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }
        $storeId = (int) $store['id'];
        $row = (new StoreVitrineCategoryRepository())->find($id, $storeId);
        if (!$row) {
            $this->json(['error' => 'Categoria não encontrada'], 404);

            return;
        }
        $key = vitrine_category_icon_normalize_key((string) ($row['icon_key'] ?? 'acessorios'));
        $category = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'icon_key' => $key,
            'icon_url' => vitrine_category_icon_url($key),
        ];
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $products = $service->listForStoreByCategory($storeId, $id, true);
        $this->json([
            'html' => $this->renderView('store/_dynamic_category', [
                'store' => $store,
                'category' => $category,
                'products' => $products,
            ]),
            'title' => $category['name'] . ' — ' . $store['name'],
            'category_id' => $id,
            'mode' => 'category',
        ]);
    }

    public function create(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }
        $this->requireGerenteOfStore($storeId);

        $input = $this->getJsonInput();
        $name = trim((string) ($input['name'] ?? ''));
        $iconKey = vitrine_category_icon_normalize_key(trim((string) ($input['icon_key'] ?? '')));

        if ($name === '') {
            $this->json(['error' => 'Informe o nome da categoria.'], 400);

            return;
        }
        if (mb_strlen($name) > 80) {
            $this->json(['error' => 'O nome pode ter no máximo 80 caracteres.'], 400);

            return;
        }
        if (!vitrine_category_icon_is_valid($iconKey)) {
            $this->json(['error' => 'Escolha um ícone válido para a categoria.'], 400);

            return;
        }

        $repo = new StoreVitrineCategoryRepository();
        if ($repo->countByStore($storeId) >= self::MAX_PER_STORE) {
            $this->json(['error' => 'Limite de ' . self::MAX_PER_STORE . ' categorias por loja.'], 400);

            return;
        }
        if ($repo->nameExists($storeId, $name)) {
            $this->json(['error' => 'Já existe uma categoria com este nome.'], 400);

            return;
        }

        $sortOrder = $repo->countByStore($storeId);
        $id = $repo->create($storeId, $name, $iconKey, $sortOrder);
        $row = $repo->find($id, $storeId);

        $this->json([
            'success' => true,
            'category' => $row ? $this->formatCategory($row) : null,
        ]);
    }

    public function delete(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);

            return;
        }
        $this->requireGerenteOfStore($storeId);

        $repo = new StoreVitrineCategoryRepository();
        if (!$repo->delete($id, $storeId)) {
            $this->json(['error' => 'Categoria não encontrada.'], 404);

            return;
        }

        $this->json(['success' => true]);
    }

    private function getStoreRowBySlug(string $slug): ?array
    {
        return (new StoreRepository())->findBySlug($slug) ?: null;
    }

    private function renderView(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require PLATAFORM_BACKEND . '/views/' . $view . '.php';

        return (string) ob_get_clean();
    }

    private function formatCategory(array $row): array
    {
        $key = vitrine_category_icon_normalize_key((string) ($row['icon_key'] ?? 'acessorios'));

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'icon_key' => $key,
            'icon_url' => vitrine_category_icon_url($key),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}
