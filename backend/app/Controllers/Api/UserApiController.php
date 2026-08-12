<?php

namespace App\Controllers\Api;

use App\Auth\Permissions;
use App\Controllers\Controller;
use App\Database\Database;
use App\Repositories\EmployeeRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StoreMemberRepository;
use App\Repositories\UserRepository;

/**
 * Equipe da loja.
 *
 * Trabalha sobre `store_members`: a pessoa é um registro único em `users` e o
 * cargo é o vínculo com esta loja. Antes, "criar funcionário" criava uma LINHA
 * NOVA em `users` com senha própria, e "excluir funcionário" apagava essa
 * linha — hoje isso apagaria a pessoa inteira, com o histórico de compras dela
 * em outras lojas. Excluir passou a desfazer o vínculo.
 */
class UserApiController extends Controller
{
    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $tipo = $_GET['user_type'] ?? null;
        $tipo = in_array($tipo, [Permissions::GERENTE, Permissions::FUNCIONARIO], true) ? $tipo : null;
        $users = (new StoreMemberRepository())->listMembers($storeId, $tipo);
        $roleRepo = new EmployeeRoleRepository();
        foreach ($users as &$u) {
            $roles = $roleRepo->getRolesByUser((int) $u['id']);
            $u['cargo'] = $roles[0]['name'] ?? null;
        }
        unset($u);
        $this->json(['users' => $users]);
    }

    /**
     * Adiciona alguém à equipe.
     *
     * Se o e-mail já tem conta na plataforma, a pessoa é apenas vinculada —
     * mantendo a senha que já usa. Antes isso criava uma segunda conta com
     * outra senha, e ela passava a ter duas identidades.
     */
    public function create(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role = $this->normalizarCargo($input['user_type'] ?? Permissions::FUNCIONARIO);

        if ($name === '' || $email === '') {
            $this->json(['error' => 'Nome e e-mail são obrigatórios'], 400);
            return;
        }

        $userRepo = new UserRepository();
        $memberRepo = new StoreMemberRepository();
        $existente = $userRepo->findByEmail($email);

        if ($existente !== null && $memberRepo->role((int) $existente['id'], $storeId) !== null) {
            $this->json(['error' => 'Esta pessoa já faz parte da equipe desta loja.'], 400);
            return;
        }
        if ($existente === null && $password === '') {
            $this->json(['error' => 'Informe uma senha para a nova conta.'], 400);
            return;
        }

        try {
            $userId = Database::transaction(function () use ($existente, $userRepo, $memberRepo, $name, $email, $password, $storeId, $role): int {
                if ($existente !== null) {
                    $id = (int) $existente['id'];
                } else {
                    $id = $userRepo->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                }
                $memberRepo->upsert($id, $storeId, $role);

                return $id;
            });
        } catch (\Throwable $e) {
            log_exception($e, ['acao' => 'criar-membro', 'store_id' => $storeId]);
            $this->json(['error' => 'Não foi possível adicionar à equipe.'], 400);
            return;
        }

        $user = $userRepo->find($userId);
        unset($user['password']);
        $user['user_type'] = $role;
        $this->json([
            'success' => true,
            'user' => $user,
            'vinculado' => $existente !== null,
            'mensagem' => $existente !== null
                ? 'Esta pessoa já tinha conta na plataforma e foi vinculada à loja com a senha que já usa.'
                : 'Conta criada e vinculada à loja.',
        ]);
    }

    public function update(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $memberRepo = new StoreMemberRepository();
        $cargoAtual = $memberRepo->role($id, $storeId);
        if ($cargoAtual === null) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $repo = new UserRepository();

        $dados = [];
        if (array_key_exists('name', $input)) {
            $dados['name'] = trim((string) $input['name']);
        }
        if (array_key_exists('email', $input)) {
            $novoEmail = trim((string) $input['email']);
            $dono = $repo->findByEmail($novoEmail);
            if ($dono !== null && (int) $dono['id'] !== $id) {
                $this->json(['error' => 'Já existe outra conta com este e-mail.'], 400);
                return;
            }
            $dados['email'] = $novoEmail;
        }
        if (!empty($input['password'])) {
            $dados['password'] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
        }

        $novoCargo = array_key_exists('user_type', $input) ? $this->normalizarCargo($input['user_type']) : null;
        if ($novoCargo !== null && $novoCargo !== $cargoAtual && $cargoAtual === Permissions::GERENTE) {
            // Rebaixar o último gerente deixaria a loja sem quem a administre.
            if ($memberRepo->countByRole($storeId, Permissions::GERENTE) <= 1) {
                $this->json(['error' => 'Esta loja precisa de pelo menos um gerente.'], 400);
                return;
            }
        }

        Database::transaction(function () use ($repo, $memberRepo, $id, $dados, $novoCargo, $storeId): void {
            if ($dados !== []) {
                $repo->update($id, $dados);
            }
            if ($novoCargo !== null) {
                $memberRepo->upsert($id, $storeId, $novoCargo);
            }
        });
        Permissions::limparCache();

        $user = $repo->find($id);
        unset($user['password']);
        $user['user_type'] = $novoCargo ?? $cargoAtual;
        $this->json(['success' => true, 'user' => $user]);
    }

    public function assignRoles(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        if ((new StoreMemberRepository())->role($id, $storeId) === null) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $roleIds = $this->getJsonInput()['role_ids'] ?? [];
        $roleRepo = new RoleRepository();
        foreach ($roleIds as $rid) {
            $role = $roleRepo->find((int) $rid);
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
        if ((new StoreMemberRepository())->role($id, $storeId) === null) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        $this->json(['roles' => (new EmployeeRoleRepository())->getRolesByUser($id)]);
    }

    public function delete(string $slug, int $id): void
    {
        $this->removerDaEquipe($slug, $id);
    }

    /** Remove da equipe recebendo user_id no body (POST /api/loja/{slug}/users/delete). */
    public function deleteByBody(string $slug): void
    {
        $input = $this->getJsonInput();
        $this->removerDaEquipe($slug, (int) ($input['user_id'] ?? $input['id'] ?? 0));
    }

    /**
     * Desfaz o vínculo com a loja — não apaga a pessoa.
     *
     * Apagar o registro de `users` levaria junto a conta que ela usa como
     * cliente, com os endereços e o histórico de compras em outras lojas.
     */
    private function removerDaEquipe(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        if ($id <= 0) {
            $this->json(['error' => 'Informe o ID do usuário (user_id).'], 400);
            return;
        }
        $memberRepo = new StoreMemberRepository();
        $cargo = $memberRepo->role($id, $storeId);
        if ($cargo === null) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }
        if ($cargo === Permissions::GERENTE && $memberRepo->countByRole($storeId, Permissions::GERENTE) <= 1) {
            $this->json(['error' => 'Esta loja precisa de pelo menos um gerente.'], 400);
            return;
        }
        if ((int) ($_SESSION['logged_user_id'] ?? 0) === $id) {
            $this->json(['error' => 'Você não pode remover a si mesmo da equipe.'], 400);
            return;
        }
        $memberRepo->remove($id, $storeId);
        (new EmployeeRoleRepository())->setUserRoles($id, []);
        Permissions::limparCache();
        $this->json(['success' => true]);
    }

    private function normalizarCargo($valor): string
    {
        $v = strtolower(trim((string) $valor));

        return $v === Permissions::GERENTE ? Permissions::GERENTE : Permissions::FUNCIONARIO;
    }
}
