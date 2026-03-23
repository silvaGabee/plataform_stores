<?php

namespace App\Controllers;

abstract class Controller
{
    protected function json($data, int $code = 200): void
    {
        json_response($data, $code);
    }

    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if (!$input) return [];
        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function getStoreIdFromSlug(string $slug): ?int
    {
        $repo = new \App\Repositories\StoreRepository();
        $store = $repo->findBySlug($slug);
        return $store ? (int) $store['id'] : null;
    }

    /** Exige que o usuário logado seja gerente desta loja. Retorna 403 JSON se não for. */
    protected function requireGerenteOfStore(int $storeId): void
    {
        if (!logged_in() || !is_gerente_store((int) $storeId)) {
            $this->json(['error' => 'Acesso negado. Apenas o gerente desta loja pode acessar.'], 403);
            exit;
        }
    }

    /**
     * Exige que o usuário logado seja gerente OU funcionário desta loja (mesmo store_id).
     * Usado nas APIs do painel para quem acessa pela loja correta.
     */
    protected function requireStorePanelAccess(int $storeId): void
    {
        if (!logged_in() || !can_access_store_panel((int) $storeId)) {
            $this->json(['error' => 'Acesso negado. Faça login como gerente ou funcionário desta loja.'], 403);
            exit;
        }
    }
}
