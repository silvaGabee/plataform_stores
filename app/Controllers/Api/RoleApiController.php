<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Repositories\EmployeeRoleRepository;

class RoleApiController extends Controller
{
    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new RoleRepository();
        $empRepo = new EmployeeRoleRepository();
        $roles = $repo->listByStore($storeId);
        foreach ($roles as &$r) {
            $r['users'] = $empRepo->getUsersByRole((int) $r['id']);
        }
        unset($r);
        $this->json(['roles' => $roles]);
    }

    public function hierarchy(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new RoleRepository();
        $empRepo = new EmployeeRoleRepository();
        $tree = $repo->getHierarchy($storeId);
        $addUsers = function (array &$nodes) use ($empRepo, &$addUsers) {
            foreach ($nodes as &$n) {
                $n['users'] = $empRepo->getUsersByRole((int) $n['id']);
                if (!empty($n['children'])) {
                    $addUsers($n['children']);
                }
            }
            unset($n);
        };
        $addUsers($tree);
        $this->json(['hierarchy' => $tree]);
    }

    public function create(string $slug): void
    {
        try {
            $storeId = $this->getStoreIdFromSlug($slug);
            if (!$storeId) {
                $this->json(['error' => 'Loja não encontrada'], 404);
            }
            $this->requireGerenteOfStore($storeId);
            $input = $this->getJsonInput();
            if (!is_array($input)) {
                $input = [];
            }
            $name = trim((string) ($input['name'] ?? ''));
            $parentRaw = $input['parent_role_id'] ?? null;
            $parentRoleId = null;
            if ($parentRaw !== null && $parentRaw !== '') {
                $parentRoleId = (int) $parentRaw;
                if ($parentRoleId < 1) {
                    $parentRoleId = null;
                }
            }
            if ($name === '') {
                $this->json(['error' => 'Nome do cargo é obrigatório'], 400);
            }
            if ($parentRoleId !== null) {
                $repoCheck = new RoleRepository();
                $parent = $repoCheck->find($parentRoleId);
                if (!$parent || (int) ($parent['store_id'] ?? 0) !== $storeId) {
                    $this->json(['error' => 'Cargo superior inválido'], 400);
                }
            }
            $repo = new RoleRepository();
            $id = $repo->create([
                'store_id'       => $storeId,
                'name'           => $name,
                'parent_role_id' => $parentRoleId,
            ]);
            $userIds = $input['user_ids'] ?? [];
            if (is_array($userIds) && $id > 0) {
                $empRepo = new EmployeeRoleRepository();
                $empRepo->setRoleUsers($id, $userIds);
            }
            $role = $id > 0 ? $repo->find($id) : null;
            $this->json(['success' => true, 'role' => $role]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Erro ao criar cargo: ' . $e->getMessage()], 500);
        }
    }

    public function update(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $repo = new RoleRepository();
        $role = $repo->find($id);
        if (!$role || (int) $role['store_id'] !== $storeId) {
            $this->json(['error' => 'Cargo não encontrado'], 404);
        }
        $newParentId = isset($input['parent_role_id']) ? (trim((string) $input['parent_role_id']) === '' ? null : (int) $input['parent_role_id']) : $role['parent_role_id'];
        if ($newParentId !== null && $newParentId === (int) $id) {
            $this->json(['error' => 'Um cargo não pode ser superior de si mesmo. Escolha outro cargo ou deixe sem superior.'], 400);
        }
        if ($newParentId !== null) {
            $parent = $repo->find($newParentId);
            if (!$parent || (int) $parent['store_id'] !== $storeId) {
                $this->json(['error' => 'Cargo superior inválido'], 400);
            }
        }
        $repo->update($id, [
            'name'           => $input['name'] ?? $role['name'],
            'parent_role_id' => $newParentId,
        ]);
        $userIds = $input['user_ids'] ?? null;
        if (is_array($userIds)) {
            $empRepo = new EmployeeRoleRepository();
            $empRepo->setRoleUsers((int) $id, $userIds);
        }
        $this->json(['success' => true, 'role' => $repo->find($id)]);
    }

    public function delete(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $repo = new RoleRepository();
        $role = $repo->find($id);
        if (!$role || (int) $role['store_id'] !== $storeId) {
            $this->json(['error' => 'Cargo não encontrado'], 404);
        }
        $repo->delete($id);
        $this->json(['success' => true]);
    }

    /**
     * Cria um organograma de exemplo (CEO → Comercial, Administrativo, Produção → subcargos).
     * Apenas gerente. Não apaga cargos existentes; adiciona os que faltam por nome.
     */
    public function seedExample(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $repo = new RoleRepository();
        $existing = $repo->listByStore($storeId);
        $byName = [];
        foreach ($existing as $r) {
            $byName[strtolower(trim($r['name'] ?? ''))] = (int) $r['id'];
        }
        $created = 0;
        $id = function ($name) use (&$byName, $repo, $storeId, &$created) {
            $key = strtolower(trim($name));
            if (isset($byName[$key])) {
                return $byName[$key];
            }
            $newId = $repo->create(['store_id' => $storeId, 'name' => $name, 'parent_role_id' => null]);
            $byName[$key] = $newId;
            $created++;
            return $newId;
        };
        $idParent = function ($name, $parentId) use (&$byName, $repo, $storeId, &$created) {
            $key = strtolower(trim($name));
            if (isset($byName[$key])) {
                return $byName[$key];
            }
            $newId = $repo->create(['store_id' => $storeId, 'name' => $name, 'parent_role_id' => $parentId]);
            $byName[$key] = $newId;
            $created++;
            return $newId;
        };
        $ceo = $id('CEO');
        $comercial = $idParent('Comercial', $ceo);
        $administrativo = $idParent('Administrativo', $ceo);
        $producao = $idParent('Produção', $ceo);
        $idParent('Marketing', $comercial);
        $idParent('Vendas', $comercial);
        $idParent('Financeiro', $administrativo);
        $idParent('Operacional', $producao);
        $idParent('Compras', $producao);
        $this->json(['success' => true, 'created' => $created, 'message' => 'Organograma de exemplo criado. Total de cargos novos: ' . $created]);
    }
}
