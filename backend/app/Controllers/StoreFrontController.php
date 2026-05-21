<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\UserRepository;
use App\Services\ProductService;
use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserAddressRepository;
use App\Repositories\StoreVitrineCategoryRepository;

class StoreFrontController extends Controller
{
    public function vitrine(string $slug): void
    {
        $store = $this->getStore($slug);
        $service = new ProductService(new ProductRepository(), new \App\Repositories\StockMovementRepository(), new ProductImageRepository());
        $products = $service->listForStore((int) $store['id'], true);
        $this->render('store/vitrine', [
            'store' => $store,
            'products' => $products,
            'vitrine_categories' => $this->loadVitrineCategories((int) $store['id']),
            'title' => $store['name'],
        ]);
    }

    public function categoria(string $slug, string $id): void
    {
        $store = $this->getStore($slug);
        $categoryId = (int) $id;
        $catRepo = new StoreVitrineCategoryRepository();
        $row = $catRepo->find($categoryId, (int) $store['id']);
        if (!$row) {
            http_response_code(404);
            echo 'Categoria não encontrada';

            return;
        }
        $key = vitrine_category_icon_normalize_key((string) ($row['icon_key'] ?? 'acessorios'));
        $category = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'icon_key' => $key,
            'icon_url' => vitrine_category_icon_url($key),
        ];
        $service = new ProductService(new ProductRepository(), new \App\Repositories\StockMovementRepository(), new ProductImageRepository());
        $products = $service->listForStoreByCategory((int) $store['id'], $categoryId, true);
        $this->render('store/categoria', [
            'store' => $store,
            'category' => $category,
            'products' => $products,
            'vitrine_categories' => $this->loadVitrineCategories((int) $store['id']),
            'title' => $category['name'] . ' — ' . $store['name'],
        ]);
    }

    /** @return list<array{id: int, name: string, icon_key: string, icon_url: string}> */
    private function loadVitrineCategories(int $storeId): array
    {
        $vitrineCategories = [];
        foreach ((new StoreVitrineCategoryRepository())->listByStore($storeId) as $row) {
            $key = vitrine_category_icon_normalize_key((string) ($row['icon_key'] ?? 'acessorios'));
            $vitrineCategories[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'icon_key' => $key,
                'icon_url' => vitrine_category_icon_url($key),
            ];
        }

        return $vitrineCategories;
    }

    public function product(string $slug, string $id): void
    {
        $store = $this->getStore($slug);
        $service = new ProductService(new ProductRepository(), new \App\Repositories\StockMovementRepository(), new ProductImageRepository());
        $product = $service->getByIdAndStore((int) $id, (int) $store['id']);
        if (!$product) {
            http_response_code(404);
            echo 'Produto não encontrado';
            return;
        }
        $vitrineCategory = null;
        $catId = (int) ($product['vitrine_category_id'] ?? 0);
        if ($catId > 0) {
            $catRepo = new \App\Repositories\StoreVitrineCategoryRepository();
            $vitrineCategory = $catRepo->find($catId, (int) $store['id']);
        }
        $this->render('store/produto', [
            'store' => $store,
            'product' => $product,
            'vitrineCategory' => $vitrineCategory,
            'title' => $product['name'],
        ]);
    }

    public function cart(string $slug): void
    {
        $store = $this->getStore($slug);
        $cart = $_SESSION['cart'][$store['id']] ?? [];
        $this->render('store/carrinho', ['store' => $store, 'cart' => $cart, 'title' => 'Carrinho']);
    }

    public function checkout(string $slug): void
    {
        $store = $this->getStore($slug);
        $cart = $_SESSION['cart'][$store['id']] ?? [];
        if (empty($cart)) {
            redirect(base_url("loja/{$slug}/carrinho"));
        }
        $checkoutCustomerName = '';
        $checkoutCustomerEmail = '';
        if (logged_in()) {
            $userId = (int) ($_SESSION['logged_user_id'] ?? $_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $user = (new UserRepository())->find($userId);
                if ($user) {
                    $checkoutCustomerName = (string) ($user['name'] ?? '');
                    $checkoutCustomerEmail = (string) ($user['email'] ?? '');
                }
            }
        }
        $this->render('store/checkout', [
            'store' => $store,
            'cart' => $cart,
            'title' => 'Finalizar compra',
            'checkout_customer_name' => $checkoutCustomerName,
            'checkout_customer_email' => $checkoutCustomerEmail,
        ]);
    }

    public function order(string $slug, string $id): void
    {
        $store = $this->getStore($slug);
        $orderRepo = new OrderRepository();
        $order = $orderRepo->findByIdAndStore((int) $id, $store['id']);
        if (!$order) {
            http_response_code(404);
            echo 'Pedido não encontrado';
            return;
        }
        $order['items'] = (new OrderItemRepository())->getByOrder($order['id']);
        $order['payments'] = (new PaymentRepository())->findByOrder($order['id']);
        $orderAddress = null;
        if (!empty($order['address_id'])) {
            $orderAddress = (new \App\Repositories\UserAddressRepository())->find((int) $order['address_id']);
        }
        $this->render('store/pedido', ['store' => $store, 'order' => $order, 'order_address' => $orderAddress, 'title' => 'Pedido #' . $order['id']]);
    }

    public function meusPedidos(string $slug): void
    {
        $store = $this->getStore($slug);
        $loginEmail = $this->getEmailForStoreCustomer($store);
        $email = $loginEmail !== null ? $loginEmail : trim((string) ($_GET['email'] ?? ''));
        $orders = [];
        $emailSearched = false;
        $logged_in_used = $loginEmail !== null;
        if ($email !== '') {
            $emailSearched = true;
            $user = (new UserRepository())->findByEmailAndStore($email, (int) $store['id']);
            if ($user) {
                $orders = (new OrderRepository())->listByCustomerNotDelivered((int) $store['id'], (int) $user['id']);
            }
        }
        $this->render('store/meus_pedidos', [
            'store' => $store,
            'title' => 'Meus pedidos',
            'email' => $email,
            'email_searched' => $emailSearched,
            'orders' => $orders,
            'logged_in_used' => $logged_in_used,
        ]);
    }

    public function meusEnderecos(string $slug): void
    {
        $store = $this->getStore($slug);
        $loginEmail = $this->getEmailForStoreCustomer($store);
        $email = $loginEmail !== null ? $loginEmail : trim((string) ($_GET['email'] ?? ''));
        $addresses = [];
        $emailSearched = false;
        $logged_in_used = $loginEmail !== null;
        $user = null;
        if ($email !== '') {
            $emailSearched = true;
            $user = (new UserRepository())->findByEmailAndStore($email, (int) $store['id']);
            if ($user) {
                $addresses = (new UserAddressRepository())->getByUserId((int) $user['id']);
            }
        }
        $customerName = '';
        if ($loginEmail !== null) {
            $userId = (int) ($_SESSION['logged_user_id'] ?? $_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $loggedUser = (new UserRepository())->find($userId);
                if ($loggedUser) {
                    $customerName = (string) ($loggedUser['name'] ?? '');
                }
            }
        }
        if ($customerName === '' && $user) {
            $customerName = (string) ($user['name'] ?? '');
        }
        $this->render('store/meus_enderecos', [
            'store' => $store,
            'title' => 'Meus endereços',
            'email' => $email,
            'email_searched' => $emailSearched,
            'addresses' => $addresses,
            'logged_in_used' => $logged_in_used,
            'customer_name' => $customerName,
        ]);
    }

    /** Retorna o e-mail do usuário logado (para validar Meus pedidos/endereços) ou null se não logado. */
    private function getEmailForStoreCustomer(array $store): ?string
    {
        if (!logged_in()) {
            return null;
        }
        $userId = (int) ($_SESSION['logged_user_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        $user = (new UserRepository())->find($userId);
        return $user && !empty($user['email']) ? trim((string) $user['email']) : null;
    }

    private function getStore(string $slug): array
    {
        $repo = new StoreRepository();
        $store = $repo->findBySlug($slug);
        if (!$store) {
            http_response_code(404);
            echo 'Loja não encontrada';
            exit;
        }
        return $store;
    }

    private function render(string $view, array $data = []): void
    {
        if (isset($data['store'])) {
            $data['can_see_panel'] = can_access_store_panel((int) $data['store']['id']);
        }
        extract($data);
        require PLATAFORM_BACKEND . "/views/{$view}.php";
    }
}
