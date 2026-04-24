<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;

class PanelController extends Controller
{
    public function dashboard(string $slug): void
    {
        $store = $this->getStore($slug);
        $welcomeUserName = $this->getLoggedUserName();
        $this->render('panel/dashboard', [
            'store' => $store,
            'title' => 'Painel',
            'welcome_user_name' => $welcomeUserName,
        ]);
    }

    public function products(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->render('panel/produtos', ['store' => $store, 'title' => 'Produtos']);
    }

    public function stock(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->render('panel/estoque', ['store' => $store, 'title' => 'Estoque']);
    }

    public function entregas(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->render('panel/entregas', ['store' => $store, 'title' => 'Entregas']);
    }

    public function pdv(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->render('panel/pdv', ['store' => $store, 'title' => 'PDV', 'pdv_user_name' => $this->getLoggedUserName()]);
    }

    public function employees(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->requireGerenteOrRedirect($store, $slug);
        $this->render('panel/funcionarios', ['store' => $store, 'title' => 'Funcionários']);
    }

    public function clientes(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->requireGerenteOrRedirect($store, $slug);
        $this->render('panel/clientes', ['store' => $store, 'title' => 'Clientes']);
    }

    public function hierarchy(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->render('panel/hierarquia', ['store' => $store, 'title' => 'Hierarquia']);
    }

    public function settings(string $slug): void
    {
        $store = $this->getStore($slug);
        $this->requireGerenteOrRedirect($store, $slug);
        $this->render('panel/configuracoes', ['store' => $store, 'title' => 'Configurações']);
    }

    /** Funcionário não acessa estas rotas; redireciona ao dashboard. */
    private function requireGerenteOrRedirect(array $store, string $slug): void
    {
        if (is_funcionario_panel_readonly((int) $store['id'])) {
            redirect(base_url("painel/{$slug}"));
        }
    }

    private function getLoggedUserName(): string
    {
        $uid = (int) ($_SESSION['logged_user_id'] ?? 0);
        if ($uid <= 0) {
            return '';
        }
        $user = (new UserRepository())->find($uid);
        if ($user && !empty($user['name'])) {
            return (string) $user['name'];
        }
        return '';
    }

    private function getStore(string $slug): array
    {
        if (!logged_in()) {
            redirect(base_url());
        }
        $repo = new StoreRepository();
        $store = $repo->findBySlug($slug);
        if (!$store) {
            http_response_code(404);
            echo 'Loja não encontrada';
            exit;
        }
        if (!can_access_store_panel((int) $store['id'])) {
            redirect(base_url("loja/{$slug}"));
        }
        return $store;
    }

    private function render(string $view, array $data = []): void
    {
        $store = $data['store'] ?? null;
        if (is_array($store) && isset($store['id'])) {
            $data['panel_readonly'] = is_funcionario_panel_readonly((int) $store['id']);
            $data['panel_is_gerente'] = is_gerente_store((int) $store['id']);
        } else {
            $data['panel_readonly'] = false;
            $data['panel_is_gerente'] = true;
        }
        extract($data);
        require PLATAFORM_BACKEND . "/views/{$view}.php";
    }
}
