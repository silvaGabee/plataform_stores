<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Database\Database;
use App\Services\StoreService;
use App\Repositories\StoreRepository;
use App\Repositories\StorePixConfigRepository;
use App\Repositories\StoreDashboardConfigRepository;
use App\Repositories\UserRepository;

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

    /**
     * Exclui a loja e todos os dados vinculados (irreversível). Apenas gerente.
     * Corpo JSON: { "confirmation": "Excluir" } (texto exato).
     */
    public function deleteStore(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        if (trim((string) ($input['confirmation'] ?? '')) !== 'Excluir') {
            $this->json(['error' => 'Digite Excluir para confirmar.'], 400);
            return;
        }
        $userRepo = new UserRepository();
        $sessionUid = (int) ($_SESSION['logged_user_id'] ?? 0);
        $sessionEmail = '';
        if ($sessionUid > 0) {
            $u = $userRepo->find($sessionUid);
            $sessionEmail = (string) ($u['email'] ?? '');
        }
        $pdo = Database::getConnection();
        $repo = new StoreRepository();
        try {
            $pdo->beginTransaction();
            $userRepo->detachUsersForDeletedStore($storeId);
            if (!$repo->delete($storeId)) {
                $pdo->rollBack();
                $this->json(['error' => 'Não foi possível excluir a loja.'], 500);
                return;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->json(['error' => 'Não foi possível excluir a loja. Verifique se não há dependências bloqueando a exclusão.'], 500);
            return;
        }
        $this->restoreSessionAfterStoreDeleted($sessionEmail, $storeId);
        $this->json(['success' => true, 'redirect' => base_url('lojas')]);
    }

    /** Mantém a sessão: reatribui ao utilizador de plataforma (mesmo e-mail) se o id da sessão foi removido. */
    private function restoreSessionAfterStoreDeleted(string $sessionEmail, int $deletedStoreId): void
    {
        if ($sessionEmail === '') {
            logout();
            return;
        }
        $userRepo = new UserRepository();
        $rows = $userRepo->findAllByEmail($sessionEmail);
        $pick = null;
        foreach ($rows as $r) {
            $sid = $r['store_id'] ?? null;
            if ($sid === null || $sid === '') {
                $pick = $r;
                break;
            }
        }
        if ($pick === null && $rows !== []) {
            $pick = $rows[0];
        }
        if ($pick === null) {
            logout();
            return;
        }
        $_SESSION['logged_user_id'] = (int) $pick['id'];
        $_SESSION['user_id'] = (int) $pick['id'];
        $loggedStore = $pick['store_id'] ? (int) $pick['store_id'] : null;
        if ($loggedStore === $deletedStoreId) {
            $loggedStore = null;
        }
        $_SESSION['logged_store_id'] = $loggedStore;
    }
}
