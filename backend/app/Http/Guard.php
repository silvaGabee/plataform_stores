<?php

namespace App\Http;

use App\Auth\Permissions;
use App\Repositories\StoreRepository;
use RuntimeException;

/**
 * Porta única de entrada: valida CSRF e permissão antes de o controller rodar.
 *
 * O modelo é NEGAR POR PADRÃO. Toda rota declara o que exige no terceiro
 * elemento do seu handler; rota sem declaração levanta exceção em vez de
 * executar. Antes, cada controller lembrava (ou esquecia) de chamar o guard na
 * primeira linha — e endpoint sem a chamada nascia aberto, em silêncio.
 */
final class Guard
{
    /** Rota aberta a qualquer visitante (vitrine, carrinho, catálogo público). */
    public const PUBLICO = 'public';

    /** Basta estar autenticado; a checagem fina fica no controller. */
    public const AUTENTICADO = 'auth';

    /** Métodos que alteram estado e por isso exigem token CSRF. */
    private const METODOS_INSEGUROS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param string               $pattern      padrão da rota, ex.: 'POST /api/loja/{slug}/products'
     * @param array                $handler      [Classe, metodo, requisito]
     * @param array<string,string> $paramsNomeados ex.: ['slug' => 'minha-loja', 'id' => '7']
     */
    public static function autorizar(string $pattern, array $handler, array $paramsNomeados, bool $isApi): void
    {
        $requisito = $handler[2] ?? null;
        if (!is_string($requisito) || $requisito === '') {
            // Erro de configuração, não do visitante: a rota existe mas ninguém
            // declarou quem pode chamá-la. Fechar é a única resposta segura.
            throw new RuntimeException(
                'Rota sem requisito de acesso declarado: ' . $pattern
                . '. Informe o terceiro elemento do handler (ex.: Guard::PUBLICO ou "store.catalog.write").'
            );
        }

        if (in_array(self::metodo(), self::METODOS_INSEGUROS, true)) {
            self::exigirCsrf($isApi);
        }

        if ($requisito === self::PUBLICO) {
            return;
        }

        if (!logged_in()) {
            self::recusar($isApi, 401, 'Faça login para continuar.');
        }

        if ($requisito === self::AUTENTICADO) {
            return;
        }

        if (!Permissions::existe($requisito)) {
            throw new RuntimeException(
                'Rota ' . $pattern . ' declara a permissão desconhecida "' . $requisito . '".'
            );
        }

        $storeId = self::resolverLoja($paramsNomeados, $isApi);
        if (!Permissions::can($requisito, $storeId)) {
            if (!$isApi) {
                // Numa página, mandar a pessoa para onde ela PODE ir vale mais
                // que um 403 seco — é o que o painel já fazia com o funcionário
                // que tentava abrir Configurações.
                self::redirecionarSemPermissao($paramsNomeados, $storeId);
            }
            self::recusar($isApi, 403, 'Você não tem permissão para esta ação.');
        }
    }

    /** Devolve o usuário ao lugar mais próximo a que tem acesso. */
    private static function redirecionarSemPermissao(array $paramsNomeados, int $storeId): void
    {
        $slug = (string) ($paramsNomeados['slug'] ?? '');
        $_SESSION['_error'] = 'Você não tem permissão para acessar essa área.';
        if ($slug !== '' && Permissions::can('store.panel.view', $storeId)) {
            redirect(base_url('painel/' . rawurlencode($slug)));
        }
        if ($slug !== '') {
            redirect(base_url('loja/' . rawurlencode($slug)));
        }
        redirect(base_url());
    }

    /**
     * Converte o {slug} da rota no id da loja.
     * Toda permissão `store.*` é relativa a uma loja concreta.
     */
    private static function resolverLoja(array $paramsNomeados, bool $isApi): int
    {
        $slug = trim((string) ($paramsNomeados['slug'] ?? ''));
        if ($slug === '') {
            throw new RuntimeException('Permissão de loja exigida numa rota sem parâmetro {slug}.');
        }
        $store = (new StoreRepository())->findBySlug($slug);
        if (!$store) {
            self::recusar($isApi, 404, 'Loja não encontrada.');
        }

        return (int) $store['id'];
    }

    /**
     * Confere o token CSRF.
     *
     * Sem isto, qualquer site conseguia disparar ações em nome de quem
     * estivesse logado, já que a autenticação é só o cookie de sessão. Os
     * formulários enviam o campo _csrf; o JavaScript envia o cabeçalho
     * X-CSRF-Token (ver frontend/public/assets/js/csrf.js).
     */
    private static function exigirCsrf(bool $isApi): void
    {
        $esperado = (string) ($_SESSION['_csrf'] ?? '');
        $recebido = self::tokenRecebido();
        // hash_equals: comparação em tempo constante.
        if ($esperado === '' || $recebido === '' || !hash_equals($esperado, $recebido)) {
            // 403 e não 419: o Apache converte códigos não registrados em 500,
            // e aí a resposta chegaria como erro de servidor. O cabeçalho abaixo
            // é o que permite ao JavaScript distinguir "token vencido"
            // (recarregar resolve) de "sem permissão" (recarregar não resolve).
            if (!$isApi) {
                self::voltarComSessaoExpirada();
            }
            header('X-CSRF-Retry: 1');
            self::recusar($isApi, 403, 'Sessão expirada ou pedido inválido. Recarregue a página e tente novamente.');
        }
    }

    /**
     * Formulário de página enviado com o token vencido.
     *
     * A sessão do PHP expira depois de um tempo sem uso. Quem deixava a tela
     * de entrar aberta e enviava depois recebia uma página crua de 403, e
     * "recarregar" dava 404 (o /login só aceita POST). Agora a pessoa volta
     * para o formulário de onde veio, com um aviso e o e-mail ou nome já
     * preenchidos. A senha nunca é guardada.
     *
     * Nada do que foi enviado é processado: só o redirecionamento acontece,
     * então a proteção contra CSRF continua igual.
     *
     * @return never
     */
    private static function voltarComSessaoExpirada(): void
    {
        $_SESSION['_error'] = 'Sua sessão expirou. Confira os dados e tente de novo.';
        $guardar = [];
        foreach (['email', 'name'] as $campo) {
            if (isset($_POST[$campo]) && is_string($_POST[$campo])) {
                $guardar[$campo] = mb_substr(trim($_POST[$campo]), 0, 190);
            }
        }
        $_SESSION['_old'] = $guardar;
        $cadastro = ($_POST['auth_intent'] ?? '') === 'register';

        // 1. Formulário de entrada de uma loja: volta para a tela dela.
        $origem = (string) ($_POST['origem'] ?? '');
        if ($origem !== '' && preg_match('/^[a-z0-9-]+$/', $origem) === 1 && (new StoreRepository())->findBySlug($origem)) {
            redirect(base_url('loja/' . $origem . '/entrar' . ($cadastro ? '?modo=cadastro' : '')));
        }
        // 2. Entrada ou cadastro da plataforma: reabre a janela certa.
        if (isset($_POST['auth_intent'])) {
            redirect(base_url('?auth=' . ($cadastro ? 'cadastro' : 'login')));
        }
        // 3. Outro formulário de página: volta para onde estava, se for deste site.
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $base = rtrim(base_url(), '/') . '/';
        if ($referer !== '' && strpos($referer, $base) === 0) {
            redirect($referer);
        }
        redirect(base_url());
        // redirect() já encerra; o exit explícito deixa isso visível para o
        // PHPStan, que não sabe que a função nunca retorna.
        exit;
    }

    private static function tokenRecebido(): string
    {
        $cabecalho = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($cabecalho) && $cabecalho !== '') {
            return trim($cabecalho);
        }
        if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
            return trim($_POST['_csrf']);
        }
        // PUT/DELETE com corpo JSON: o token pode vir no próprio JSON quando
        // não houver como definir cabeçalho.
        $bruto = file_get_contents('php://input');
        if (is_string($bruto) && $bruto !== '') {
            $json = json_decode($bruto, true);
            if (is_array($json) && isset($json['_csrf']) && is_string($json['_csrf'])) {
                return trim($json['_csrf']);
            }
        }

        return '';
    }

    private static function metodo(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * URL absoluta do pedido atual, para voltar a ela depois do login.
     *
     * Montada a partir de REQUEST_URI, que já traz o caminho completo — passá-la
     * por base_url() duplicaria o prefixo da instalação. Quem consome
     * (_after_login) confere o prefixo antes de redirecionar, então não vira
     * open redirect.
     */
    private static function urlAtual(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return $host !== '' ? ($https ? 'https' : 'http') . '://' . $host . $uri : base_url();
    }

    /** Encerra a requisição com a resposta adequada ao tipo de rota. */
    private static function recusar(bool $isApi, int $codigo, string $mensagem): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($codigo);
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                ['error' => $mensagem, 'login_required' => $codigo === 401],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }
        if ($codigo === 401) {
            $_SESSION['_error'] = $mensagem;
            $_SESSION['_after_login'] = self::urlAtual();
            redirect(base_url('?auth=login'));
        }
        if ($codigo === 404) {
            NotFound::pagina('Loja não encontrada', 'Confira o endereço. A loja pode ter mudado de nome ou saído do ar.');
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>' . $codigo . '</h1><p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }
}
