<?php

namespace App\Auth;

use App\Repositories\UserRepository;

/**
 * Quem pode o quê, num lugar só.
 *
 * Antes esta decisão estava espalhada por três funções soltas
 * (is_gerente_store, can_access_store_panel, is_funcionario_panel_readonly)
 * chamadas à mão em mais de cem endpoints — e a restrição do funcionário
 * existia apenas na interface: as APIs de escrita aceitavam `funcionario`, de
 * modo que bastava chamar a rota direto para criar ou apagar produto.
 *
 * A matriz abaixo é a fonte da verdade. O Guard a consulta antes de qualquer
 * controller rodar; as funções antigas viraram invólucros finos sobre ela para
 * que as views continuem funcionando.
 */
final class Permissions
{
    /** Cargos possíveis dentro de uma loja. */
    public const GERENTE = 'gerente';
    public const FUNCIONARIO = 'funcionario';

    /**
     * Permissão => cargos que a possuem.
     *
     * A divisão segue o que o painel já comunicava ao funcionário:
     * ele OPERA a loja (PDV, caixa, entregas) mas não a GERENCIA
     * (catálogo, estoque, equipe, BI, configurações).
     *
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        // Entrar no painel e ver os números do dia.
        'store.panel.view'      => [self::GERENTE, self::FUNCIONARIO],

        // Catálogo e estoque: o funcionário vê, o gerente altera.
        'store.catalog.read'    => [self::GERENTE, self::FUNCIONARIO],
        'store.catalog.write'   => [self::GERENTE],

        // Pedidos e entregas: o funcionário movimenta o kanban.
        'store.orders.read'     => [self::GERENTE, self::FUNCIONARIO],
        'store.orders.write'    => [self::GERENTE, self::FUNCIONARIO],

        // Frente de caixa.
        'store.pdv.operate'     => [self::GERENTE, self::FUNCIONARIO],
        'store.cash.operate'    => [self::GERENTE, self::FUNCIONARIO],

        // Confirmar pagamento é do gerente. O funcionário tem uma exceção
        // estreita — dinheiro em pedido do PDV — resolvida dentro de
        // PaymentApiController::confirm, porque depende do pagamento concreto.
        'store.payments.read'   => [self::GERENTE],
        'store.payments.confirm' => [self::GERENTE, self::FUNCIONARIO],

        // Relatórios operacionais o funcionário vê; o BI é do gerente.
        'store.reports.read'    => [self::GERENTE, self::FUNCIONARIO],
        'store.bi.read'         => [self::GERENTE],

        // Metas: o funcionário vê as suas, só o gerente define.
        'store.goals.read'      => [self::GERENTE, self::FUNCIONARIO],
        'store.goals.write'     => [self::GERENTE],

        // Equipe e hierarquia.
        'store.users.manage'    => [self::GERENTE],
        'store.roles.read'      => [self::GERENTE, self::FUNCIONARIO],
        'store.roles.manage'    => [self::GERENTE],

        // Identidade e configurações da loja — inclui a chave PIX do lojista.
        // Só gerente, inclusive na leitura: a tela de Configurações e o editor
        // de banner ficam no ramo exclusivo do gerente, então nada que o
        // funcionário enxerga consome estes endpoints. Antes a leitura era
        // liberada aos dois e a chave PIX ficava ao alcance de um GET.
        'store.settings.read'   => [self::GERENTE],
        'store.settings.write'  => [self::GERENTE],

        // Assistente de IA do painel (consome API paga).
        'store.ai.use'          => [self::GERENTE, self::FUNCIONARIO],
    ];

    /** @return list<string> todas as permissões conhecidas */
    public static function todas(): array
    {
        return array_keys(self::MATRIZ);
    }

    public static function existe(string $permissao): bool
    {
        return isset(self::MATRIZ[$permissao]);
    }

    /**
     * O usuário logado tem esta permissão nesta loja?
     *
     * Resolve o cargo pelo registro de `users` da sessão, exigindo que ele
     * pertença à loja em questão — gerente de outra loja não tem acesso a esta.
     * (A pessoa ainda pode ter várias linhas em `users`, uma por loja; unificar
     * isso é a Fase 3 do plano em docs/.)
     */
    public static function can(string $permissao, int $storeId): bool
    {
        if (!isset(self::MATRIZ[$permissao])) {
            // Permissão desconhecida nunca é concedida: um erro de digitação
            // no nome deve fechar a rota, não abri-la.
            return false;
        }
        $cargo = self::cargoNaLoja($storeId);
        if ($cargo === null) {
            return false;
        }

        return in_array($cargo, self::MATRIZ[$permissao], true);
    }

    /** Cargo do usuário logado nesta loja, ou null se não tem nenhum. */
    public static function cargoNaLoja(int $storeId): ?string
    {
        $userId = (int) ($_SESSION['logged_user_id'] ?? 0);
        if ($userId < 1 || $storeId < 1) {
            return null;
        }
        $user = self::carregarUsuario($userId);
        if ($user === null) {
            return null;
        }
        $rawStoreId = $user['store_id'] ?? null;
        if ($rawStoreId === null || $rawStoreId === '' || (int) $rawStoreId !== $storeId) {
            return null;
        }
        $tipo = strtolower(trim((string) ($user['user_type'] ?? '')));

        return in_array($tipo, [self::GERENTE, self::FUNCIONARIO], true) ? $tipo : null;
    }

    /**
     * Cache por requisição.
     *
     * can() é chamada várias vezes por página (menu, botões, guarda de rota) e
     * cada chamada fazia um SELECT em `users`.
     *
     * @var array<int, array|null>
     */
    private static array $cacheUsuarios = [];

    /** @return array<string, mixed>|null */
    private static function carregarUsuario(int $userId): ?array
    {
        if (!array_key_exists($userId, self::$cacheUsuarios)) {
            self::$cacheUsuarios[$userId] = (new UserRepository())->find($userId);
        }

        return self::$cacheUsuarios[$userId];
    }

    /** Esquece o cache — necessário após login, logout ou troca de cargo. */
    public static function limparCache(): void
    {
        self::$cacheUsuarios = [];
    }
}
