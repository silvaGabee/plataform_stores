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

    /**
     * Normaliza De/Até para relatórios (YYYY-MM-DD). Strings vazias ou inválidas viram intervalo padrão (últimos 30 dias).
     *
     * @return array{0: string, 1: string}
     */
    protected function parseReportDateRange(?string $from, ?string $to): array
    {
        $from = trim((string) ($from ?? ''));
        $to = trim((string) ($to ?? ''));
        $valid = static function (string $d): bool {
            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        };
        if (!$valid($from)) {
            $from = '';
        }
        if (!$valid($to)) {
            $to = '';
        }
        if ($from === '' && $to === '') {
            $to = date('Y-m-d');
            $from = date('Y-m-d', strtotime('-30 days', strtotime($to)));

            return [$from, $to];
        }
        if ($from === '') {
            $from = date('Y-m-d', strtotime('-30 days', strtotime($to)));
        }
        if ($to === '') {
            $to = date('Y-m-d', strtotime('+30 days', strtotime($from)));
            $today = date('Y-m-d');
            if (strcmp($to, $today) > 0) {
                $to = $today;
            }
        }
        if (strcmp($from, $to) > 0) {
            return [$to, $from];
        }

        return [$from, $to];
    }
}
