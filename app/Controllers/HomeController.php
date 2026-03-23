<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Services\StoreService;
use App\Repositories\StorePixConfigRepository;
use App\Repositories\UserRepository;

class HomeController extends Controller
{
    public function index(): void
    {
        if (logged_in()) {
            redirect(base_url('lojas'));
        }
        $this->render('login', ['title' => 'Entrar']);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha e-mail e senha.';
            redirect(base_url());
        }
        $userRepo = new UserRepository();
        $candidates = $userRepo->findAllByEmail($email);
        foreach ($candidates as $user) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['logged_user_id'] = (int) $user['id'];
                $_SESSION['logged_store_id'] = $user['store_id'] ? (int) $user['store_id'] : null;
                $_SESSION['user_id'] = (int) $user['id'];
                redirect(base_url('lojas'));
            }
        }
        $_SESSION['_old'] = ['email' => $email];
        $_SESSION['_error'] = 'E-mail ou senha incorretos.';
        redirect(base_url());
    }

    public function listStores(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $userRepo = new UserRepository();
        $me = $userRepo->find((int) $_SESSION['logged_user_id']);
        $email = $me['email'] ?? '';
        $myStoreIds = $email !== '' ? $userRepo->findStaffStoreIdsByEmail($email) : [];
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
            'title'            => 'Lojas existentes',
            'my_stores'        => $myStores,
            'available_stores' => $availableStores,
        ]);
    }

    public function createStoreForm(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $this->render('create_store', ['title' => 'Criar minha loja']);
    }

    public function createStore(): void
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $managerEmail = trim($_POST['manager_email'] ?? '');
        $managerPassword = $_POST['manager_password'] ?? '';
        if (!$name || !$managerName || !$managerEmail || !$managerPassword) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha pelo menos: Nome da loja, Seu nome, E-mail e Senha.';
            redirect(base_url('criar-loja'));
        }
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
            ]);
            $_SESSION['store_slug'] = $store['slug'];
            $user = (new UserRepository())->findByEmailAndStore($managerEmail, $store['id']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['logged_user_id'] = (int) $user['id'];
            $_SESSION['logged_store_id'] = (int) $store['id'];
            redirect(base_url("loja/{$store['slug']}"));
        } catch (\Throwable $e) {
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
        $this->render('create_account', ['title' => 'Criar minha conta']);
    }

    public function createAccount(): void
    {
        if (logged_in()) {
            redirect(base_url('lojas'));
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Preencha todos os campos.';
            redirect(base_url('criar-conta'));
        }
        $userRepo = new UserRepository();
        if ($userRepo->findByEmail($email, null) !== null) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = 'Este e-mail já está cadastrado.';
            redirect(base_url('criar-conta'));
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
            redirect(base_url());
        } catch (\Throwable $e) {
            $_SESSION['_old'] = $_POST;
            $_SESSION['_error'] = $e->getMessage();
            redirect(base_url('criar-conta'));
        }
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        require dirname(__DIR__, 2) . "/views/{$view}.php";
    }
}
