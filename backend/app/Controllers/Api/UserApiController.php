<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Repositories\EmployeeRoleRepository;
use App\Repositories\RoleRepository;

class UserApiController extends Controller
{
    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $type = $_GET['user_type'] ?? null;
        $repo = new UserRepository();
        $users = $type ? $repo->listByStore($storeId, $type) : $repo->listEmployeesByStore($storeId);
        $roleRepo = new EmployeeRoleRepository();
        foreach ($users as &$u) {
            unset($u['password']);
            $roles = $roleRepo->getRolesByUser((int) $u['id']);
            $u['cargo'] = isset($roles[0]['name']) ? $roles[0]['name'] : null;
        }
        $this->json(['users' => $users]);
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
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $userType = $input['user_type'] ?? 'funcionario';
        if (!$name || !$email || !$password) {
            $this->json(['error' => 'Nome, email e senha são obrigatórios'], 400);
            return;
        }
        $repo = new UserRepository();
        if ($repo->findByEmailAndStore($email, $storeId)) {
            $this->json(['error' => 'Já existe usuário com este e-mail na loja'], 400);
            return;
        }
        $userId = $repo->create([
            'store_id'   => $storeId,
            'name'       => $name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'user_type'  => $userType,
        ]);
        $user = $repo->find($userId);
        unset($user['password']);
        $this->json(['success' => true, 'user' => $user]);
    }

    public function update(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $repo = new UserRepository();
        $user = $repo->find($id);
        if (!$user || (int) $user['store_id'] !== $storeId) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $data = [];
        if (array_key_exists('name', $input)) $data['name'] = $input['name'];
        if (array_key_exists('email', $input)) $data['email'] = $input['email'];
        if (!empty($input['password'])) $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        if (array_key_exists('user_type', $input)) $data['user_type'] = $input['user_type'];
        $repo->update($id, $data);
        $user = $repo->find($id);
        unset($user['password']);
        $this->json(['success' => true, 'user' => $user]);
    }

    public function assignRoles(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $roleIds = $input['role_ids'] ?? [];
        $userRepo = new UserRepository();
        $user = $userRepo->find($id);
        if (!$user || (int) $user['store_id'] !== $storeId) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $roleRepo = new RoleRepository();
        foreach ($roleIds as $rid) {
            $role = $roleRepo->find($rid);
            if (!$role || (int) $role['store_id'] !== $storeId) {
                $this->json(['error' => 'Cargo inválido'], 400);
                return;
            }
        }
        (new EmployeeRoleRepository())->setUserRoles($id, $roleIds);
        $this->json(['success' => true]);
    }

    public function getRoles(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $userRepo = new UserRepository();
        $user = $userRepo->find($id);
        if (!$user || (int) $user['store_id'] !== $storeId) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $roles = (new EmployeeRoleRepository())->getRolesByUser($id);
        $this->json(['roles' => $roles]);
    }

    public function delete(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        if ($id <= 0) {
            $this->json(['error' => 'ID do usuário inválido.'], 400);
            return;
        }
        $repo = new UserRepository();
        $user = $repo->find($id);
        if (!$user || (int) $user['store_id'] !== $storeId) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $repo->delete($id);
        $this->json(['success' => true]);
    }

    /** Exclui funcionário recebendo user_id no body (POST /api/loja/{slug}/users/delete). */
    public function deleteByBody(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $id = (int) ($input['user_id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Informe o ID do usuário (user_id).'], 400);
            return;
        }
        $repo = new UserRepository();
        $user = $repo->find($id);
        if (!$user || (int) $user['store_id'] !== $storeId) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $repo->delete($id);
        $this->json(['success' => true]);
    }
}
