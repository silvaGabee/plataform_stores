<?php

namespace App\Services;

use App\Repositories\StoreRepository;
use App\Repositories\StorePixConfigRepository;
use App\Repositories\UserRepository;

class StoreService
{
    public function __construct(
        private StoreRepository $storeRepo,
        private StorePixConfigRepository $pixConfigRepo,
        private UserRepository $userRepo
    ) {}

    public function createStore(array $data): array
    {
        $slug = trim($data['slug'] ?? '');
        if ($slug === '') {
            $slug = slugify($data['name']);
        } else {
            $slug = slugify($slug);
        }
        $baseSlug = $slug;
        $counter = 0;
        while ($this->storeRepo->slugExists($slug)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
        $data['slug'] = $slug;
        $storeId = $this->storeRepo->create($data);
        $this->pixConfigRepo->create(['store_id' => $storeId]);
        if (!empty($data['manager_name']) && !empty($data['manager_email']) && !empty($data['manager_password'])) {
            $hash = password_hash($data['manager_password'], PASSWORD_DEFAULT);
            $existingId = isset($data['existing_manager_user_id']) ? (int) $data['existing_manager_user_id'] : 0;
            if ($existingId > 0) {
                $user = $this->userRepo->find($existingId);
                if (
                    !$user
                    || strcasecmp(trim((string) $user['email']), trim((string) $data['manager_email'])) !== 0
                ) {
                    throw new \InvalidArgumentException('Não foi possível associar a loja à sua conta.');
                }
                $rawStoreId = $user['store_id'] ?? null;
                $hasNoStore = $rawStoreId === null || $rawStoreId === '' || (int) $rawStoreId === 0;
                if ($hasNoStore) {
                    $this->userRepo->update($existingId, [
                        'store_id'  => $storeId,
                        'user_type' => 'gerente',
                        'password'  => $hash,
                        'name'      => $data['manager_name'],
                    ]);
                } else {
                    $this->userRepo->create([
                        'store_id'  => $storeId,
                        'name'      => $data['manager_name'],
                        'email'     => $data['manager_email'],
                        'password'  => $hash,
                        'user_type' => 'gerente',
                    ]);
                }
            } else {
                $this->userRepo->create([
                    'store_id'  => $storeId,
                    'name'      => $data['manager_name'],
                    'email'     => $data['manager_email'],
                    'password'  => $hash,
                    'user_type' => 'gerente',
                ]);
            }
        }
        return $this->storeRepo->find($storeId);
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->storeRepo->findBySlug($slug);
    }
}
