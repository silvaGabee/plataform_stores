<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Services\StoreService;
use App\Repositories\StorePixConfigRepository;
use App\Auth\RateLimiter;
use App\Repositories\StoreMemberRepository;
use App\Repositories\UserRepository;

class HomeController extends Controller
{
    /** Tentativas de login por IP+e-mail antes de bloquear, e a janela em segundos. */
    private const LOGIN_TENTATIVAS = 8;
    private const LOGIN_JANELA = 900;

    /** Contas criadas a partir do mesmo IP por hora. */
    private const CADASTRO_TENTATIVAS = 5;
    private const CADASTRO_JANELA = 3600;

    public function index(): void
    {
        if (logged_in()) {
            redirect(base_url('lojas'));
        }
        $this->render('home', ['title' => 'Plataforma de Lojas']);
    }

    public function login(): void
    {
        $intent = $_POST['auth_intent'] ?? null;
        if ($intent !== null && $intent !== 'login') {
            $_SESSION['_old'] = $_POST;
            if ($intent === 'register') {
                $_SESSION['_error'] = 'Para criar uma conta nova, use o separador «Criar conta» no formulário.';
                redirect(base_url('?auth=cadastro'));
            }
            $_SESSION['_error'] = 'Pedido de entrada inválido. Tente novamente.';
            redirect(base_url('?auth=login'));
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha e-mail e senha.';
            redirect(base_url('?auth=login'));
        }
        // Sem isto, dava para testar senhas indefinidamente. 8 tentativas por
        // IP+e-mail a cada 15 minutos; o contador zera ao acertar.
        if (!RateLimiter::tentar('login', $email, self::LOGIN_TENTATIVAS, self::LOGIN_JANELA)) {
            $espera = max(1, (int) ceil(RateLimiter::esperaSegundos('login', $email) / 60));
            log_message('warning', 'Login bloqueado por excesso de tentativas', [
                'ip' => RateLimiter::ip(),
            ]);
            $_SESSION['_old'] = ['email' => $email];
            $_SESSION['_error'] = 'Muitas tentativas de entrada. Aguarde ' . $espera . ' minuto(s) e tente novamente.';
            redirect(base_url('?auth=login'));
        }

        // Uma consulta, uma pessoa. Antes isto percorria todas as linhas do
        // e-mail e entrava na primeira cuja senha casasse — com permissões
        // diferentes conforme a linha sorteada.
        $user = (new UserRepository())->findByEmail($email);
        if ($user !== null && password_verify($password, $user['password'])) {
            RateLimiter::limpar('login', $email);
            login_user($user);
            redirect($this->consumeAfterLoginUrl() ?? base_url('lojas'));
        }
        $_SESSION['_old'] = ['email' => $email];
        $_SESSION['_error'] = 'E-mail ou senha incorretos.';
        redirect(base_url('?auth=login'));
    }

    public function listStores(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        // Lojas onde a pessoa trabalha: uma consulta em store_members. Antes
        // isto procurava por e-mail as várias linhas que ela tinha em `users`.
        $myStoreIds = (new StoreMemberRepository())->storeIdsForUser((int) $_SESSION['logged_user_id']);
        $myStoreIdSet = array_flip($myStoreIds);
        $allStores = (new StoreRepository())->all();
        $myStores = [];
        $availableStores = [];
        foreach ($allStores as $s) {
            $id = (int) $s['id'];
            if (isset($myStoreIdSet[$id])) {
                $myStores[] = $s;
            } else {
                $availableStores[] = $s;
            }
        }
        $this->render('stores', [
            'title'            => 'Lojas',
            'my_stores'        => $myStores,
            'available_stores' => $availableStores,
        ]);
    }

    public function myAccount(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $user = (new UserRepository())->find((int) $_SESSION['logged_user_id']);
        if ($user === null) {
            logout();
            redirect(base_url());
        }
        // Uma pessoa pode trabalhar em várias lojas, com cargos diferentes em
        // cada uma — não existe mais "o tipo" dela.
        $vinculos = [];
        $storeRepo = new StoreRepository();
        $memberRepo = new StoreMemberRepository();
        foreach ($memberRepo->storeIdsForUser((int) $user['id']) as $storeId) {
            $loja = $storeRepo->find($storeId);
            if ($loja !== null) {
                $vinculos[] = [
                    'store_name' => (string) $loja['name'],
                    'store_slug' => (string) $loja['slug'],
                    'role' => (string) $memberRepo->role((int) $user['id'], $storeId),
                ];
            }
        }
        $this->render('my_account', [
            'title' => 'Minha conta',
            'user'  => $user,
            'vinculos' => $vinculos,
        ]);
    }

    public function deleteAccount(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $userId = (int) $_SESSION['logged_user_id'];
        $repo = new UserRepository();
        $user = $repo->find($userId);
        if ($user === null) {
            logout();
            redirect(base_url());
        }
        if ($repo->countOrdersAsCustomer($userId) > 0) {
            $_SESSION['_error'] = 'Não é possível excluir a conta porque existem pedidos associados a este utilizador.';
            redirect(base_url('minha-conta'));
        }
        if ($repo->countCashRegistersAsOpener($userId) > 0) {
            $_SESSION['_error'] = 'Não é possível excluir a conta porque existem turnos de caixa abertos por este utilizador.';
            redirect(base_url('minha-conta'));
        }
        try {
            if (!$repo->delete($userId)) {
                throw new \RuntimeException('delete failed');
            }
        } catch (\Throwable $e) {
            $_SESSION['_error'] = 'Não foi possível excluir a conta. Tente mais tarde ou contacte o suporte.';
            redirect(base_url('minha-conta'));
        }
        logout();
        $_SESSION['_success'] = 'A sua conta foi excluída com sucesso.';
        redirect(base_url('?auth=login'));
    }

    public function createStoreForm(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $userRepo = new UserRepository();
        $me = $userRepo->find((int) $_SESSION['logged_user_id']);
        if ($me === null) {
            logout();
            redirect(base_url());
        }
        $this->render('create_store', [
            'title'            => 'Criar minha loja',
            'current_user'     => $me,
            'hide_app_header'  => true,
        ]);
    }

    public function createStore(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $userRepo = new UserRepository();
        $me = $userRepo->find((int) $_SESSION['logged_user_id']);
        if ($me === null) {
            logout();
            redirect(base_url());
        }
        $name = trim($_POST['store_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $passwordConfirm = $_POST['manager_password'] ?? '';
        if ($name === '' || $passwordConfirm === '') {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha o nome da loja e confirme a sua senha.';
            redirect(base_url('criar-loja'));
        }
        if (!password_verify($passwordConfirm, $me['password'])) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Senha incorreta. Utilize a mesma palavra-passe com que faz login.';
            redirect(base_url('criar-loja'));
        }
        $managerName = trim((string) $me['name']);
        $managerEmail = trim((string) $me['email']);
        if ($managerName === '' || $managerEmail === '') {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Complete o nome e o e-mail na página Minha conta antes de criar uma loja.';
            redirect(base_url('minha-conta'));
        }
        $managerPassword = $passwordConfirm;
        $service = new StoreService(
            new StoreRepository(),
            new StorePixConfigRepository(),
            new UserRepository()
        );
        try {
            $store = $service->createStore([
                'name' => $name,
                'category' => $category ?: null,
                'city' => $city ?: null,
                'phone' => $phone ?: null,
                'manager_name' => $managerName,
                'manager_email' => $managerEmail,
                'manager_password' => $managerPassword,
                'existing_manager_user_id' => (int) $me['id'],
            ]);
            // A identidade não muda ao criar loja — ganha-se um vínculo, não
            // uma conta nova. Basta esquecer o cargo em cache para o painel
            // reconhecer o gerente recém-criado.
            \App\Auth\Permissions::limparCache();
            $_SESSION['store_slug'] = $store['slug'];
            redirect(base_url("loja/{$store['slug']}"));
        } catch (\Throwable $e) {
            log_exception($e, ['acao' => 'criar-loja', 'user_id' => (int) $me['id']]);
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = $e->getMessage();
            redirect(base_url('criar-loja'));
        }
    }

    public function logout(): void
    {
        logout();
        redirect(base_url());
    }

    public function createAccountForm(): void
    {
        if (logged_in()) {
            redirect(base_url('lojas'));
        }
        redirect(base_url('?auth=cadastro'));
    }

    public function createAccount(): void
    {
        if (logged_in()) {
            redirect(base_url('lojas'));
        }
        $intent = $_POST['auth_intent'] ?? '';
        if ($intent !== 'register') {
            $_SESSION['_old'] = $_POST;
            if ($intent === 'login') {
                $_SESSION['_error'] = 'Para entrar, use o separador «Entrar» (não cria conta nova).';
                redirect(base_url('?auth=login'));
            }
            $_SESSION['_error'] = 'Utilize o formulário «Criar conta» para se registar.';
            redirect(base_url('?auth=cadastro'));
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha todos os campos.';
            redirect(base_url('?auth=cadastro'));
        }
        // Limita criação de contas em massa a partir do mesmo IP.
        if (!RateLimiter::tentar('criar-conta', RateLimiter::ip(), self::CADASTRO_TENTATIVAS, self::CADASTRO_JANELA)) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Muitas contas criadas a partir deste acesso. Tente novamente mais tarde.';
            redirect(base_url('?auth=cadastro'));
        }
        $userRepo = new UserRepository();
        // O segundo argumento sumiu na unificação de identidade (Fase 3):
        // o e-mail é único global, não mais por loja.
        if ($userRepo->findByEmail($email) !== null) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Este e-mail já está cadastrado.';
            redirect(base_url('?auth=cadastro'));
        }
        try {
            $userRepo->create([
                'store_id' => null,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'user_type' => 'cliente',
            ]);
            $_SESSION['_success'] = 'Conta criada. Faça login para continuar.';
            redirect(base_url('?auth=login'));
        } catch (\Throwable $e) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = $e->getMessage();
            redirect(base_url('?auth=cadastro'));
        }
    }

    /**
     * Destino gravado antes de mandar a pessoa para o login (ex.: ela clicou em
     * "Finalizar compra" sem estar logada). Consome e devolve, ou null.
     *
     * A URL é sempre gerada pelo próprio servidor via base_url(), mas a
     * comparação com o prefixo continua aqui: se um dia algo escrever nesta
     * chave a partir de entrada do usuário, isto impede um open redirect.
     */
    private function consumeAfterLoginUrl(): ?string
    {
        $url = (string) ($_SESSION['_after_login'] ?? '');
        unset($_SESSION['_after_login']);
        if ($url === '') {
            return null;
        }
        $base = rtrim(base_url(), '/');

        return strpos($url, $base . '/') === 0 ? $url : null;
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        require PLATAFORM_BACKEND . "/views/{$view}.php";
    }
}
