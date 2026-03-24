<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Repositories\UserAddressRepository;

/**
 * APIs públicas do checkout (sem exigir login no painel).
 */
class CheckoutApiController extends Controller
{
    /** Lista endereços do cliente pelo e-mail (na loja). GET ?email= */
    public function addresses(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $email = trim((string) ($_GET['email'] ?? ''));
        if ($email === '') {
            $this->json(['addresses' => []]);
            return;
        }
        $userRepo = new UserRepository();
        $user = $userRepo->findByEmailAndStore($email, $storeId);
        if (!$user) {
            $this->json(['addresses' => [], 'has_user' => false]);
            return;
        }
        $addrRepo = new UserAddressRepository();
        $addresses = $addrRepo->getByUserId((int) $user['id']);
        $this->json(['addresses' => $addresses, 'has_user' => true]);
    }

    /** Cadastra um endereço para o cliente (encontra ou cria usuário pelo e-mail). */
    public function createAddress(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $email = trim($input['email'] ?? '');
        $name = trim($input['customer_name'] ?? $input['name'] ?? '');
        if ($email === '') {
            $this->json(['error' => 'E-mail é obrigatório'], 400);
            return;
        }
        $userRepo = new UserRepository();
        $user = $userRepo->findByEmailAndStore($email, $storeId);
        if (!$user) {
            $userId = $userRepo->create([
                'store_id'  => $storeId,
                'name'      => $name ?: 'Cliente',
                'email'     => $email,
                'password'  => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                'user_type' => 'cliente',
            ]);
        } else {
            $userId = (int) $user['id'];
        }
        $required = ['street', 'number', 'city', 'state', 'zipcode'];
        foreach ($required as $field) {
            if (empty(trim($input[$field] ?? ''))) {
                $this->json(['error' => 'Preencha: ' . $field], 400);
                return;
            }
        }
        $addrRepo = new UserAddressRepository();
        $id = $addrRepo->create([
            'user_id'      => $userId,
            'label'        => $input['label'] ?? null,
            'street'       => trim($input['street']),
            'number'       => trim($input['number']),
            'complement'   => trim($input['complement'] ?? '') ?: null,
            'neighborhood' => trim($input['neighborhood'] ?? '') ?: null,
            'city'         => trim($input['city']),
            'state'        => strtoupper(substr(trim($input['state']), 0, 2)),
            'zipcode'      => preg_replace('/\D/', '', trim($input['zipcode'])),
        ]);
        $address = $addrRepo->find($id);
        $this->json(['success' => true, 'address' => $address]);
    }

    /** Atualiza endereço do cliente (e-mail deve ser do dono do endereço na loja). */
    public function updateAddress(string $slug, string $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $addressId = (int) $id;
        if ($addressId <= 0) {
            $this->json(['error' => 'Endereço inválido'], 400);
            return;
        }
        $input = $this->getJsonInput();
        $email = trim($input['email'] ?? '');
        if ($email === '') {
            $this->json(['error' => 'E-mail é obrigatório'], 400);
            return;
        }
        $userRepo = new UserRepository();
        $user = $userRepo->findByEmailAndStore($email, $storeId);
        if (!$user) {
            $this->json(['error' => 'Cliente não encontrado'], 404);
            return;
        }
        $addrRepo = new UserAddressRepository();
        if (!$addrRepo->belongsToUser($addressId, (int) $user['id'])) {
            $this->json(['error' => 'Endereço não encontrado'], 404);
            return;
        }
        $required = ['street', 'number', 'city', 'state', 'zipcode'];
        foreach ($required as $field) {
            if (empty(trim($input[$field] ?? ''))) {
                $this->json(['error' => 'Preencha: ' . $field], 400);
                return;
            }
        }
        $label = trim((string) ($input['label'] ?? ''));
        $addrRepo->update($addressId, [
            'label'        => $label !== '' ? $label : null,
            'street'       => trim($input['street']),
            'number'       => trim($input['number']),
            'complement'   => trim($input['complement'] ?? '') ?: null,
            'neighborhood' => trim($input['neighborhood'] ?? '') ?: null,
            'city'         => trim($input['city']),
            'state'        => strtoupper(substr(trim($input['state']), 0, 2)),
            'zipcode'      => preg_replace('/\D/', '', trim($input['zipcode'])),
        ]);
        $address = $addrRepo->find($addressId);
        $this->json(['success' => true, 'address' => $address]);
    }

    /** Remove endereço do cliente (e-mail deve ser do dono na loja). */
    public function deleteAddress(string $slug, string $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $addressId = (int) $id;
        if ($addressId <= 0) {
            $this->json(['error' => 'Endereço inválido'], 400);
            return;
        }
        $input = $this->getJsonInput();
        $email = trim($input['email'] ?? '');
        if ($email === '') {
            $this->json(['error' => 'E-mail é obrigatório'], 400);
            return;
        }
        $userRepo = new UserRepository();
        $user = $userRepo->findByEmailAndStore($email, $storeId);
        if (!$user) {
            $this->json(['error' => 'Cliente não encontrado'], 404);
            return;
        }
        $addrRepo = new UserAddressRepository();
        if (!$addrRepo->belongsToUser($addressId, (int) $user['id'])) {
            $this->json(['error' => 'Endereço não encontrado'], 404);
            return;
        }
        if (!$addrRepo->delete($addressId)) {
            $this->json(['error' => 'Não foi possível excluir'], 500);
            return;
        }
        $this->json(['success' => true]);
    }
}
