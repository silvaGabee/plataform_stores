<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
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
        // Finalizar compra exige conta. O checkout antes identificava a pessoa
        // pelo e-mail digitado no formulário — e esse mesmo e-mail dava acesso
        // aos endereços e pedidos de qualquer cliente.
        $me = $this->currentUser();
        if ($me === null) {
            $_SESSION['_error'] = 'Entre na sua conta para finalizar a compra.';
            $_SESSION['_after_login'] = base_url("loja/{$slug}/checkout");
            redirect(base_url('?auth=login'));
        }
        $checkoutCustomerName = (string) ($me['name'] ?? '');
        $checkoutCustomerEmail = (string) ($me['email'] ?? '');
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
        if (!$order || !$this->canViewOrder($order, (int) $store['id'])) {
            // 404 e não 403: confirmar que o pedido existe já é informação útil
            // para quem está varrendo ids sequenciais.
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

    /**
     * "Meus pedidos" e "Meus endereços" aceitavam ?email= e listavam os dados
     * de quem quer que fosse aquele e-mail. Agora dependem exclusivamente da
     * sessão; sem login, vão para a tela de entrada.
     */
    public function meusPedidos(string $slug): void
    {
        $store = $this->getStore($slug);
        $me = $this->requireCustomerLogin($slug, 'meus-pedidos');
        $orders = (new OrderRepository())->listByCustomersNotDelivered(
            (int) $store['id'],
            $this->currentUserIdentityIds()
        );
        // O link do comprovante precisa levar o token junto.
        foreach ($orders as &$order) {
            $order['order_url'] = $this->orderUrl($store, $order);
        }
        unset($order);
        $this->render('store/meus_pedidos', [
            'store' => $store,
            'title' => 'Meus pedidos',
            'email' => (string) ($me['email'] ?? ''),
            'orders' => $orders,
        ]);
    }

    public function meusEnderecos(string $slug): void
    {
        $store = $this->getStore($slug);
        $me = $this->requireCustomerLogin($slug, 'meus-enderecos');
        $addresses = (new UserAddressRepository())->getByUserIds($this->currentUserIdentityIds());
        $this->render('store/meus_enderecos', [
            'store' => $store,
            'title' => 'Meus endereços',
            'email' => (string) ($me['email'] ?? ''),
            'addresses' => $addresses,
            'customer_name' => (string) ($me['name'] ?? ''),
        ]);
    }

    /** Exige sessão de cliente; sem ela manda para o login e volta depois. */
    private function requireCustomerLogin(string $slug, string $path): array
    {
        $me = $this->currentUser();
        if ($me === null) {
            $_SESSION['_error'] = 'Entre na sua conta para ver esta página.';
            $_SESSION['_after_login'] = base_url("loja/{$slug}/{$path}");
            redirect(base_url('?auth=login'));
        }

        return $me;
    }

    /**
     * Quem pode abrir a página de um pedido.
     *
     * Três caminhos, nesta ordem: quem trabalha na loja; quem está logado e é o
     * dono; e quem tem o link com o token (o comprovante que o cliente recebe
     * ao finalizar a compra, que continua funcionando depois do logout).
     */
    private function canViewOrder(array $order, int $storeId): bool
    {
        if (logged_in() && can_access_store_panel($storeId)) {
            return true;
        }
        if ($this->userOwnsOrder($order)) {
            return true;
        }
        $expected = (string) ($order['access_token'] ?? '');
        $given = trim((string) ($_GET['t'] ?? ''));

        // hash_equals: comparação em tempo constante, para o tempo de resposta
        // não revelar quantos caracteres do token estão certos.
        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }

    /** URL do comprovante já com o token, para os links internos. */
    private function orderUrl(array $store, array $order): string
    {
        $url = base_url("loja/{$store['slug']}/pedido/{$order['id']}");
        $token = (string) ($order['access_token'] ?? '');

        return $token !== '' ? $url . '?t=' . rawurlencode($token) : $url;
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
