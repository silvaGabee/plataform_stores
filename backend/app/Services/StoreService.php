<?php

namespace App\Services;

use App\Repositories\StoreRepository;
use App\Repositories\StorePixConfigRepository;
use App\Repositories\UserRepository;
use App\Repositories\StoreDashboardConfigRepository;

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

        // Loja, configuração de PIX e vínculo do gerente nascem juntos ou não
        // nascem: uma falha no meio deixaria uma loja sem quem a administre.
        return \App\Database\Database::transaction(function () use ($data): array {
            $storeId = $this->storeRepo->create($data);
            $this->pixConfigRepo->create(['store_id' => $storeId]);

            $email = trim((string) ($data['manager_email'] ?? ''));
            if ($email !== '') {
                $this->vincularGerente($storeId, $email, $data);
            }

            return $this->storeRepo->find($storeId);
        });
    }

    /**
     * Torna a pessoa gerente da loja recém-criada.
     *
     * Antes, isto criava uma LINHA NOVA em `users` sempre que quem já tinha uma
     * loja criasse outra — a mesma pessoa passava a ter duas contas, com senhas
     * independentes. Agora é só um vínculo em `store_members`, e a senha
     * existente permanece intocada.
     */
    private function vincularGerente(int $storeId, string $email, array $data): void
    {
        $memberRepo = new \App\Repositories\StoreMemberRepository();
        $existenteId = isset($data['existing_manager_user_id']) ? (int) $data['existing_manager_user_id'] : 0;

        if ($existenteId > 0) {
            $user = $this->userRepo->find($existenteId);
            if (!$user || strcasecmp(trim((string) $user['email']), $email) !== 0) {
                throw new \InvalidArgumentException('Não foi possível associar a loja à sua conta.');
            }
            $memberRepo->upsert($existenteId, $storeId, 'gerente');

            return;
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null) {
            if (empty($data['manager_password'])) {
                throw new \InvalidArgumentException('Informe uma senha para a conta do gerente.');
            }
            $userId = $this->userRepo->create([
                'name'     => $data['manager_name'] ?? 'Gerente',
                'email'    => $email,
                'password' => password_hash((string) $data['manager_password'], PASSWORD_DEFAULT),
            ]);
        } else {
            $userId = (int) $user['id'];
        }
        $memberRepo->upsert($userId, $storeId, 'gerente');
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->storeRepo->findBySlug($slug);
    }

    /** Atualiza o nome de exibição da loja (o slug/URL da vitrine não muda). */
    public function updateStoreName(int $storeId, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Informe o nome da loja.');
        }
        if (mb_strlen($name) < 2) {
            throw new \InvalidArgumentException('O nome deve ter pelo menos 2 caracteres.');
        }
        if (mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('O nome pode ter no máximo 120 caracteres.');
        }
        $store = $this->storeRepo->find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Loja não encontrada.');
        }
        if (! $this->storeRepo->updateName($storeId, $name)) {
            throw new \RuntimeException('Não foi possível guardar o nome.');
        }
        $updated = $this->storeRepo->find($storeId);
        return $updated ?: array_merge($store, ['name' => $name]);
    }

    /** Atualiza o slogan exibido abaixo do nome na vitrine (vazio = ocultar). */
    public function updateStoreSlogan(int $storeId, string $slogan): array
    {
        $slogan = trim($slogan);
        if ($slogan !== '' && mb_strlen($slogan) > 160) {
            throw new \InvalidArgumentException('O slogan pode ter no máximo 160 caracteres.');
        }
        $store = $this->storeRepo->find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Loja não encontrada.');
        }
        $value = $slogan === '' ? null : $slogan;
        if (! $this->storeRepo->updateSlogan($storeId, $value)) {
            throw new \RuntimeException('Não foi possível guardar o slogan.');
        }
        $updated = $this->storeRepo->find($storeId);
        return $updated ?: array_merge($store, ['slogan' => $value]);
    }

    public function updateStoreBackgroundColor(int $storeId, ?string $color): array
    {
        $color = trim((string) $color);
        if ($color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Informe uma cor em formato hexadecimal válido: #RRGGBB.');
        }
        $store = $this->storeRepo->find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Loja não encontrada.');
        }
        $value = $color === '' ? null : $color;
        if (! $this->storeRepo->updateBackgroundColor($storeId, $value)) {
            throw new \RuntimeException('Não foi possível guardar a cor de fundo.');
        }
        $updated = $this->storeRepo->find($storeId);
        return $updated ?: array_merge($store, ['background_color' => $value]);
    }

    /**
     * Update appearance settings (categories/banner colors) stored in dashboard config.
     * Returns the saved appearance array.
     */
    public function updateStoreAppearance(int $storeId, array $appearance): array
    {
        $store = $this->storeRepo->find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Loja não encontrada.');
        }
        $repo = new StoreDashboardConfigRepository();
        $cfg = $repo->getConfig($storeId);
        if (!is_array($cfg)) $cfg = [];
        $cfg['appearance'] = $cfg['appearance'] ?? [];
        // validate hex colors (allow empty to unset)
        foreach (['categories_background_color', 'banner_background_color'] as $k) {
            if (array_key_exists($k, $appearance)) {
                $val = trim((string) ($appearance[$k] ?? ''));
                if ($val !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
                    throw new \InvalidArgumentException('Cor inválida para ' . $k . '. Use #RRGGBB.');
                }
                $cfg['appearance'][$k] = $val === '' ? null : $val;
            }
        }
        $repo->setConfig($storeId, $cfg);
        return $cfg['appearance'];
    }
}
