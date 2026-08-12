<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\UserAddressRepository;

/**
 * Endereços do cliente no checkout.
 *
 * Estes endpoints já aceitaram o e-mail como credencial: qualquer pessoa lia,
 * alterava e apagava o endereço de qualquer cliente passando ?email=, e o POST
 * ainda criava uma conta para o e-mail informado. A identidade agora vem
 * exclusivamente da sessão — o corpo e a query string não têm mais voz sobre
 * "de quem" é o endereço.
 */
class CheckoutApiController extends Controller
{
    /** GET — endereços do cliente logado. */
    public function addresses(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $me = $this->requireLogin();
        $addresses = (new UserAddressRepository())->getByUserId((int) $me['id']);
        $this->json(['addresses' => $addresses, 'has_user' => true]);
    }

    /** POST — cadastra um endereço para o cliente logado. */
    public function createAddress(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $me = $this->requireLogin();
        $input = $this->getJsonInput();
        $fields = $this->validateAddressInput($input);
        if ($fields === null) {
            return;
        }
        $addrRepo = new UserAddressRepository();
        $id = $addrRepo->create(['user_id' => (int) $me['id']] + $fields);
        $this->json(['success' => true, 'address' => $addrRepo->find($id)]);
    }

    /** PUT — atualiza endereço do cliente logado. */
    public function updateAddress(string $slug, string $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $me = $this->requireLogin();
        $addressId = (int) $id;
        if ($addressId <= 0) {
            $this->json(['error' => 'Endereço inválido'], 400);
            return;
        }
        $addrRepo = new UserAddressRepository();
        if (!$addrRepo->belongsToUser($addressId, (int) $me['id'])) {
            $this->json(['error' => 'Endereço não encontrado'], 404);
            return;
        }
        $fields = $this->validateAddressInput($this->getJsonInput());
        if ($fields === null) {
            return;
        }
        $addrRepo->update($addressId, $fields);
        $this->json(['success' => true, 'address' => $addrRepo->find($addressId)]);
    }

    /** DELETE — remove endereço do cliente logado. */
    public function deleteAddress(string $slug, string $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $me = $this->requireLogin();
        $addressId = (int) $id;
        if ($addressId <= 0) {
            $this->json(['error' => 'Endereço inválido'], 400);
            return;
        }
        $addrRepo = new UserAddressRepository();
        if (!$addrRepo->belongsToUser($addressId, (int) $me['id'])) {
            $this->json(['error' => 'Endereço não encontrado'], 404);
            return;
        }
        if (!$addrRepo->delete($addressId)) {
            $this->json(['error' => 'Não foi possível excluir'], 500);
            return;
        }
        $this->json(['success' => true]);
    }

    /**
     * Valida e normaliza os campos do endereço.
     * Responde 400 e devolve null quando falta campo obrigatório.
     *
     * @param array<string, mixed> $input
     * @return array<string, string|null>|null
     */
    private function validateAddressInput(array $input): ?array
    {
        foreach (['street', 'number', 'city', 'state', 'zipcode'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $this->json(['error' => 'Preencha: ' . $field], 400);
                return null;
            }
        }
        $label = trim((string) ($input['label'] ?? ''));

        return [
            'label'        => $label !== '' ? $label : null,
            'street'       => trim((string) $input['street']),
            'number'       => trim((string) $input['number']),
            'complement'   => trim((string) ($input['complement'] ?? '')) ?: null,
            'neighborhood' => trim((string) ($input['neighborhood'] ?? '')) ?: null,
            'city'         => trim((string) $input['city']),
            'state'        => strtoupper(substr(trim((string) $input['state']), 0, 2)),
            'zipcode'      => preg_replace('/\D/', '', trim((string) $input['zipcode'])),
        ];
    }
}
