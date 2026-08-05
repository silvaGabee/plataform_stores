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
     * Exige uma permissão da matriz (App\Auth\Permissions).
     *
     * O Guard já cobre as rotas cujo {slug} está na URL. Este método é para os
     * poucos casos em que a loja só é conhecida depois de ler o corpo do pedido
     * — como POST /api/ai/chat, que recebe o slug no JSON.
     */
    protected function requirePermission(string $permissao, int $storeId): void
    {
        if (!\App\Auth\Permissions::can($permissao, $storeId)) {
            $this->json(['error' => 'Você não tem permissão para esta ação.'], 403);
            exit;
        }
    }

    /** Registro completo do usuário logado, ou null se não há sessão. */
    protected function currentUser(): ?array
    {
        if (!logged_in()) {
            return null;
        }
        $userId = (int) ($_SESSION['logged_user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        return (new \App\Repositories\UserRepository())->find($userId);
    }

    /** Exige sessão iniciada. Retorna 401 JSON se não houver. */
    protected function requireLogin(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->json(['error' => 'Faça login para continuar.', 'login_required' => true], 401);
            exit;
        }

        return $user;
    }

    /**
     * Todos os ids de `users` que representam a pessoa logada.
     *
     * Enquanto a mesma pessoa tiver uma linha por loja, seus endereços e
     * pedidos ficam espalhados entre esses ids. O e-mail usado na busca vem do
     * registro da SESSÃO — nunca da requisição.
     *
     * @return int[]
     */
    protected function currentUserIdentityIds(): array
    {
        $me = $this->currentUser();
        if ($me === null) {
            return [];
        }
        $ids = [(int) $me['id']];
        $email = trim((string) ($me['email'] ?? ''));
        if ($email === '') {
            return $ids;
        }
        foreach ((new \App\Repositories\UserRepository())->findAllByEmail($email) as $row) {
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * O pedido pertence a quem está logado?
     *
     * A comparação por e-mail existe porque hoje a mesma pessoa tem uma linha
     * em `users` por loja (ver Fase 3 do plano em docs/): o pedido pode apontar
     * para a linha "da loja" enquanto a sessão está na linha "de plataforma".
     *
     * Note a diferença para o que havia no checkout: aqui o e-mail sai do
     * registro carregado pela SESSÃO, nunca de um parâmetro da requisição —
     * quem chama não escolhe de quem é o e-mail. Esta função some quando a
     * identidade for unificada.
     */
    protected function userOwnsOrder(array $order): bool
    {
        $me = $this->currentUser();
        if ($me === null) {
            return false;
        }
        $customerId = (int) ($order['customer_id'] ?? 0);
        if ($customerId < 1) {
            return false;
        }
        if ($customerId === (int) $me['id']) {
            return true;
        }
        $customer = (new \App\Repositories\UserRepository())->find($customerId);
        if ($customer === null) {
            return false;
        }
        $mine = trim((string) ($me['email'] ?? ''));

        return $mine !== ''
            && strcasecmp(trim((string) ($customer['email'] ?? '')), $mine) === 0;
    }

    /**
     * Exige ser o dono do pedido OU ter acesso ao painel da loja.
     * Responde 404 (e não 403) para quem não passa: confirmar que o pedido
     * existe já entregaria informação a quem está varrendo ids.
     */
    protected function requireOrderAccess(array $order, int $storeId): void
    {
        if (logged_in() && can_access_store_panel($storeId)) {
            return;
        }
        if ($this->userOwnsOrder($order)) {
            return;
        }
        $this->json(['error' => 'Pedido não encontrado'], 404);
        exit;
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
