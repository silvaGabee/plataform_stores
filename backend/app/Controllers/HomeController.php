<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Services\StoreService;
use App\Repositories\StorePixConfigRepository;
use App\Auth\PasswordPolicy;
use App\Auth\RateLimiter;
use App\Repositories\StoreMemberRepository;
use App\Repositories\UserRepository;

class HomeController extends Controller
{
    /** Tentativas de login por IP+e-mail antes de bloquear, e a janela em segundos. */
    private const LOGIN_TENTATIVAS = 8;
    private const LOGIN_JANELA = 900;

    /**
     * Contas criadas a partir do mesmo IP por hora.
     *
     * Era 5: numa rede compartilhada (escola, loja com Wi-Fi, operadora com
     * CGNAT) todos saem pelo mesmo IP, e o sexto cliente real era barrado.
     * 30/h ainda impede criação em massa por script.
     */
    private const CADASTRO_TENTATIVAS = 30;
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
        $loja = $this->lojaDeOrigem();
        $voltar = $this->telaDeEntrada($loja, 'login');
        $intent = $_POST['auth_intent'] ?? null;
        if ($intent !== null && $intent !== 'login') {
            $_SESSION['_old'] = $_POST;
            if ($intent === 'register') {
                $_SESSION['_error'] = 'Para criar uma conta nova, use a aba "Criar conta".';
                redirect($this->telaDeEntrada($loja, 'cadastro'));
            }
            $_SESSION['_error'] = 'Não foi possível entrar. Tente novamente.';
            redirect($voltar);
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha e-mail e senha.';
            redirect($voltar);
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
            redirect($voltar);
        }

        // Uma consulta, uma pessoa. Antes isto percorria todas as linhas do
        // e-mail e entrava na primeira cuja senha casasse — com permissões
        // diferentes conforme a linha sorteada.
        $user = (new UserRepository())->findByEmail($email);
        if ($user !== null && password_verify($password, $user['password'])) {
            RateLimiter::limpar('login', $email);
            login_user($user);
            // Quem entrou pela loja volta para ela, não para a lista de lojas
            // da plataforma (que é a tela de quem trabalha nas lojas).
            redirect($this->consumeAfterLoginUrl() ?? ($loja !== null ? base_url('loja/' . $loja['slug']) : base_url('lojas')));
        }
        $_SESSION['_old'] = ['email' => $email];
        $_SESSION['_error'] = 'E-mail ou senha incorretos.';
        redirect($voltar);
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
            $_SESSION['_error'] = 'Não é possível excluir a conta porque existem pedidos ligados a ela.';
            redirect(base_url('minha-conta'));
        }
        if ($repo->countCashRegistersAsOpener($userId) > 0) {
            $_SESSION['_error'] = 'Não é possível excluir a conta porque existem turnos de caixa abertos por ela.';
            redirect(base_url('minha-conta'));
        }
        try {
            if (!$repo->delete($userId)) {
                throw new \RuntimeException('delete failed');
            }
        } catch (\Throwable $e) {
            $_SESSION['_error'] = 'Não foi possível excluir a conta. Tente mais tarde ou fale com o suporte.';
            redirect(base_url('minha-conta'));
        }
        logout();
        $_SESSION['_success'] = 'Sua conta foi excluída.';
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
            $_SESSION['_error'] = 'Preencha o nome da loja e confirme sua senha.';
            redirect(base_url('criar-loja'));
        }
        if (!password_verify($passwordConfirm, $me['password'])) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Senha incorreta. Use a mesma senha com que você entra na plataforma.';
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
        // ?volta=loja/{slug}: quem sai pela loja continua nela.
        $volta = trim((string) ($_GET['volta'] ?? ''));
        logout();
        if (preg_match('#^loja/[a-z0-9-]+$#', $volta) === 1) {
            redirect(base_url($volta));
        }
        redirect(base_url());
    }

    /** Política de privacidade e termos de uso (link do cadastro e do rodapé das lojas). */
    public function privacy(): void
    {
        $this->render('privacidade', [
            'title' => 'Privacidade e termos de uso',
            'meta_description' => 'Como a Plataforma de Lojas e as lojas que vendem por ela usam seus dados: o que é coletado, para quê e como pedir a exclusão.',
        ]);
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
        $loja = $this->lojaDeOrigem();
        $voltar = $this->telaDeEntrada($loja, 'cadastro');
        $intent = $_POST['auth_intent'] ?? '';
        if ($intent !== 'register') {
            $_SESSION['_old'] = $_POST;
            if ($intent === 'login') {
                $_SESSION['_error'] = 'Para entrar, use a aba "Entrar".';
                redirect($this->telaDeEntrada($loja, 'login'));
            }
            $_SESSION['_error'] = 'Use o formulário "Criar conta" para se cadastrar.';
            redirect($voltar);
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha todos os campos.';
            redirect($voltar);
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Digite um e-mail válido, como nome@exemplo.com.';
            redirect($voltar);
        }
        if (($problema = PasswordPolicy::problema($password)) !== null) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = $problema;
            redirect($voltar);
        }
        // Limita criação de contas em massa a partir do mesmo IP.
        if (!RateLimiter::tentar('criar-conta', RateLimiter::ip(), self::CADASTRO_TENTATIVAS, self::CADASTRO_JANELA)) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Muitas contas criadas a partir deste acesso. Tente novamente mais tarde.';
            redirect($voltar);
        }
        $userRepo = new UserRepository();
        // O segundo argumento sumiu na unificação de identidade (Fase 3):
        // o e-mail é único global, não mais por loja.
        if ($userRepo->findByEmail($email) !== null) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Este e-mail já está cadastrado. Use a aba "Entrar".';
            redirect($voltar);
        }
        try {
            $userRepo->create([
                'store_id' => null,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'user_type' => 'cliente',
            ]);
        } catch (\Throwable $e) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = $e->getMessage();
            redirect($voltar);
        }
        if ($loja !== null) {
            // Na loja, a conta nasce no meio de uma compra: entra direto e
            // segue para onde a pessoa ia (quase sempre o checkout), em vez de
            // pedir que ela digite e-mail e senha de novo.
            $novo = $userRepo->findByEmail($email);
            if ($novo !== null) {
                login_user($novo);
                redirect($this->consumeAfterLoginUrl() ?? base_url('loja/' . $loja['slug']));
            }
        }
        $_SESSION['_success'] = 'Conta criada. Entre para continuar.';
        $_SESSION['_old'] = ['email' => $email];
        redirect($this->telaDeEntrada($loja, 'login'));
    }

    /**
     * Loja de onde veio o formulário de entrada (campo "origem" = slug).
     * Null quando veio da página da plataforma ou o slug não existe.
     */
    private function lojaDeOrigem(): ?array
    {
        $slug = trim((string) ($_POST['origem'] ?? ''));
        if ($slug === '' || preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            return null;
        }
        $loja = (new StoreRepository())->findBySlug($slug);

        return $loja ?: null;
    }

    /** Para onde voltar quando a entrada ou o cadastro falha: a loja ou a plataforma. */
    private function telaDeEntrada(?array $loja, string $aba): string
    {
        if ($loja !== null) {
            return base_url('loja/' . $loja['slug'] . '/entrar' . ($aba === 'cadastro' ? '?modo=cadastro' : ''));
        }

        return base_url('?auth=' . ($aba === 'cadastro' ? 'cadastro' : 'login'));
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
        // O aviso da tela de entrada da loja vive junto com este destino.
        unset($_SESSION['_after_login'], $_SESSION['_info']);
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
