<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\StoreService;
use App\Repositories\StoreRepository;
use App\Repositories\StorePixConfigRepository;
use App\Repositories\StoreDashboardConfigRepository;

class StoreApiController extends Controller
{
    public function getBySlug(string $slug): void
    {
        $repo = new StoreRepository();
        $store = $repo->findBySlug($slug);
        if (!$store) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        unset($store['created_at']);
        $this->json($store);
    }

    public function create(): void
    {
        $input = $this->getJsonInput();
        $required = ['name', 'manager_name', 'manager_email', 'manager_password'];
        foreach ($required as $k) {
            if (empty($input[$k])) {
                $this->json(['error' => "Campo obrigatório: {$k}"], 400);
            }
        }
        $service = new StoreService(new StoreRepository(), new StorePixConfigRepository(), new \App\Repositories\UserRepository());
        try {
            $store = $service->createStore($input);
            $this->json(['success' => true, 'store' => $store, 'redirect' => base_url("painel/{$store['slug']}")]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function getPixConfig(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new StorePixConfigRepository();
        $config = $repo->findByStore($storeId);
        $this->json(['config' => $config ?: ['pix_key' => '', 'pix_key_type' => 'aleatoria', 'merchant_name' => '', 'merchant_city' => '']]);
    }

    public function updatePixConfig(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $repo = new StorePixConfigRepository();
        $config = $repo->findByStore($storeId);
        if (!$config) {
            $repo->create(array_merge($input, ['store_id' => $storeId]));
        } else {
            $repo->update($storeId, $input);
        }
        $this->json(['success' => true]);
    }

    /** Retorna os blocos do dashboard personalizado da loja. */
    public function getDashboardConfig(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new StoreDashboardConfigRepository();
        $config = $repo->getByStore($storeId);
        $widgets = $config['widgets'] ?? [];
        $this->json(['widgets' => $widgets]);
    }

    /** Salva o layout do dashboard (lista de blocos). Apenas gerente. */
    public function updateDashboardConfig(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $widgets = isset($input['widgets']) && is_array($input['widgets']) ? $input['widgets'] : [];
        $repo = new StoreDashboardConfigRepository();
        $repo->setWidgets($storeId, $widgets);
        $this->json(['success' => true]);
    }
}
