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
            $userId = $this->userRepo->create([
                'store_id'   => $storeId,
                'name'       => $data['manager_name'],
                'email'      => $data['manager_email'],
                'password'   => password_hash($data['manager_password'], PASSWORD_DEFAULT),
                'user_type'  => 'gerente',
            ]);
        }
        return $this->storeRepo->find($storeId);
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->storeRepo->findBySlug($slug);
    }
}
