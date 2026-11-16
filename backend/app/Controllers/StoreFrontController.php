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
        $this->expirePending((int) $store['id']);
        $products = storefront_products($service->listForStore((int) $store['id'], true), (int) $store['id']);
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
            $this->naoEncontrado($store, 'Categoria não encontrada', 'Esta categoria não existe mais. Veja os produtos da vitrine.');

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
        $this->expirePending((int) $store['id']);
        $products = storefront_products($service->listForStoreByCategory((int) $store['id'], $categoryId, true), (int) $store['id']);
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
        $this->expirePending((int) $store['id']);
        $product = $service->getByIdAndStore((int) $id, (int) $store['id']);
        if (!$product) {
            // Link antigo (divulgado no Instagram, salvo nos favoritos do
            // navegador): em vez de beco sem saída, os mais vendidos da loja.
            $sugestoes = array_slice(array_values(array_filter(
                storefront_products($service->listForStore((int) $store['id'], true), (int) $store['id'], 'mais-vendidos'),
                static fn (array $p): bool => (int) ($p['stock_quantity'] ?? 0) > 0
            )), 0, 4);
            $this->naoEncontrado(
                $store,
                'Este produto não está mais disponível',
                'Ele pode ter esgotado ou saído do catálogo. Veja outras opções da loja.',
                $sugestoes
            );

            return;
        }
        // Tamanho/cor reservado por quem está pagando aparece como esgotado.
        $product = \App\Services\StockReservation::applyTo($product);
        $vitrineCategory = null;
        $catId = (int) ($product['vitrine_category_id'] ?? 0);
        if ($catId > 0) {
            $catRepo = new \App\Repositories\StoreVitrineCategoryRepository();
            $vitrineCategory = $catRepo->find($catId, (int) $store['id']);
        }

        $reviewRepo = new \App\Repositories\ReviewRepository();
        $me = $this->currentUser();
        $podeAvaliar = false;
        $minhaAvaliacao = null;
        if ($me !== null) {
            $podeAvaliar = $reviewRepo->userBoughtProduct((int) $me['id'], (int) $store['id'], (int) $product['id']);
            $minhaAvaliacao = $podeAvaliar ? $reviewRepo->findByUser((int) $product['id'], (int) $me['id']) : null;
        }

        $this->render('store/produto', [
            'store' => $store,
            'product' => $product,
            'vitrineCategory' => $vitrineCategory,
            'related_products' => $this->relatedProducts($service, (int) $store['id'], $product, $vitrineCategory ? $catId : 0),
            'review_summary' => $reviewRepo->summaryByProduct((int) $product['id']),
            'reviews' => $reviewRepo->listByProduct((int) $product['id']),
            'can_review' => $podeAvaliar,
            'my_review' => $minhaAvaliacao,
            'is_logged' => $me !== null,
            // A página do produto já tem o botão "Tirar dúvida no WhatsApp" e,
            // no celular, a barra de compra fixa no rodapé.
            'hide_whatsapp_fab' => true,
            'title' => $product['name'],
        ]);
    }

    /**
     * "Você também pode gostar": até 4 produtos da mesma categoria, os mais
     * vendidos primeiro e com estoque. Se a categoria não tiver 4, completa
     * com os mais vendidos da loja.
     */
    private function relatedProducts(ProductService $service, int $storeId, array $product, int $categoryId): array
    {
        $id = (int) $product['id'];
        $outros = static fn (array $lista): array => array_values(array_filter(
            $lista,
            static fn (array $p): bool => (int) $p['id'] !== $id && (int) ($p['stock_quantity'] ?? 0) > 0
        ));
        $lista = $categoryId > 0
            ? $outros(storefront_products($service->listForStoreByCategory($storeId, $categoryId, true), $storeId, 'mais-vendidos'))
            : [];
        if (count($lista) < 4) {
            $ja = array_fill_keys(array_map(static fn (array $p): int => (int) $p['id'], $lista), true);
            foreach ($outros(storefront_products($service->listForStore($storeId, true), $storeId, 'mais-vendidos')) as $p) {
                if (!isset($ja[(int) $p['id']])) {
                    $lista[] = $p;
                }
                if (count($lista) >= 4) {
                    break;
                }
            }
        }

        return array_slice($lista, 0, 4);
    }

    public function favoritos(string $slug): void
    {
        $store = $this->getStore($slug);
        $me = $this->requireCustomerLogin($slug, 'favoritos');
        $ids = (new \App\Repositories\FavoriteRepository())->productIds((int) $me['id'], (int) $store['id']);
        $service = new ProductService(new ProductRepository(), new \App\Repositories\StockMovementRepository(), new ProductImageRepository());
        $porId = [];
        foreach ($service->listForStore((int) $store['id'], true) as $p) {
            $porId[(int) $p['id']] = $p;
        }
        // Mantém a ordem de quando foram favoritados (mais recente primeiro).
        $produtos = [];
        foreach ($ids as $pid) {
            if (isset($porId[$pid])) {
                $produtos[] = \App\Services\StockReservation::applyTo($porId[$pid]);
            }
        }
        $this->render('store/favoritos', [
            'store' => $store,
            'products' => $produtos,
            'title' => 'Meus favoritos',
        ]);
    }

    public function cart(string $slug): void
    {
        $store = $this->getStore($slug);
        $cart = $_SESSION['cart'][$store['id']] ?? [];
        // O botão flutuante do WhatsApp cobria o "Calcular" do frete no
        // celular. Aqui a página mostra um link "Dúvidas?" no resumo.
        $this->render('store/carrinho', ['store' => $store, 'cart' => $cart, 'title' => 'Carrinho', 'hide_whatsapp_fab' => true, 'meta_robots' => 'noindex']);
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
            // A entrada acontece dentro da loja, com a marca dela. Antes ia
            // para a página da plataforma, com texto para lojistas e o aviso
            // em vermelho, como se fosse um erro.
            $_SESSION['_info'] = 'Entre ou crie sua conta para concluir o pedido. Seu carrinho fica guardado.';
            $_SESSION['_after_login'] = base_url("loja/{$slug}/checkout");
            redirect(base_url("loja/{$slug}/entrar"));
        }
        $checkoutCustomerName = (string) ($me['name'] ?? '');
        $checkoutCustomerEmail = (string) ($me['email'] ?? '');
        // Pedido do cliente ainda aguardando pagamento: o checkout avisa e leva
        // de volta ao PIX, em vez de deixar criar um pedido repetido.
        $this->expirePending((int) $store['id']);
        $pendentes = array_values(array_filter(
            (new OrderRepository())->listByCustomer((int) $store['id'], (int) $me['id'], 10),
            static fn (array $o): bool => $o['status'] === 'pendente'
        ));
        foreach ($pendentes as &$pend) {
            $pend['order_url'] = $this->orderUrl($store, $pend);
        }
        unset($pend);
        $this->render('store/checkout', [
            'store' => $store,
            'cart' => $cart,
            'title' => 'Finalizar compra',
            'hide_whatsapp_fab' => true,
            'meta_robots' => 'noindex',
            'checkout_customer_name' => $checkoutCustomerName,
            'checkout_customer_email' => $checkoutCustomerEmail,
            'pending_orders' => $pendentes,
        ]);
    }

    public function order(string $slug, string $id): void
    {
        $store = $this->getStore($slug);
        $orderRepo = new OrderRepository();
        $this->expirePending((int) $store['id']);
        $order = $orderRepo->findByIdAndStore((int) $id, $store['id']);
        if (!$order || !$this->canViewOrder($order, (int) $store['id'])) {
            // 404 e não 403: confirmar que o pedido existe já é informação útil
            // para quem está varrendo ids sequenciais.
            $this->naoEncontrado(
                $store,
                'Pedido não encontrado',
                logged_in()
                    ? 'Confira o link ou abra a lista dos seus pedidos.'
                    : 'Entre na conta usada na compra para ver seus pedidos.'
            );

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
        $this->expirePending((int) $store['id']);
        // Todos os pedidos do cliente (aguardando pagamento, em andamento,
        // entregues e cancelados). Antes só os pagos e não entregues apareciam:
        // quem gerou um PIX e saiu da página não tinha como voltar a ele.
        $orders = (new OrderRepository())->listByCustomer((int) $store['id'], (int) $me['id']);
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
        $addresses = (new UserAddressRepository())->getByUserId((int) $me['id']);
        $this->render('store/meus_enderecos', [
            'store' => $store,
            'title' => 'Meus endereços',
            'email' => (string) ($me['email'] ?? ''),
            'addresses' => $addresses,
            'customer_name' => (string) ($me['name'] ?? ''),
        ]);
    }

    /** Cancela pedidos com prazo de pagamento vencido (libera o estoque reservado). */
    private function expirePending(int $storeId): void
    {
        (new \App\Services\OrderService(
            new OrderRepository(),
            new OrderItemRepository(),
            new ProductRepository(),
            new PaymentRepository(),
            new \App\Repositories\StockMovementRepository(),
            new \App\Repositories\CashRegisterRepository(),
            new \App\Repositories\CashMovementRepository()
        ))->expirarPendentes($storeId);
    }

    /** Exige sessão de cliente; sem ela manda para a entrada da loja e volta depois. */
    private function requireCustomerLogin(string $slug, string $path): array
    {
        $me = $this->currentUser();
        if ($me === null) {
            $avisos = [
                'meus-pedidos' => 'Entre na sua conta para acompanhar seus pedidos.',
                'favoritos' => 'Entre na sua conta para ver os produtos que você salvou.',
                'meus-enderecos' => 'Entre na sua conta para ver seus endereços de entrega.',
            ];
            $_SESSION['_info'] = $avisos[$path] ?? 'Entre na sua conta para continuar.';
            $_SESSION['_after_login'] = base_url("loja/{$slug}/{$path}");
            redirect(base_url("loja/{$slug}/entrar"));
        }

        return $me;
    }

    /**
     * Entrar ou criar conta sem sair da loja.
     *
     * ?modo=cadastro abre a aba "Criar conta". ?volta= é o caminho dentro desta
     * loja para onde a pessoa vai depois de entrar (ex.: o produto que ela
     * tentou favoritar). Só caminhos que começam por loja/{slug} são aceitos,
     * para o parâmetro não virar um redirecionamento para fora do site.
     */
    public function entrar(string $slug): void
    {
        $store = $this->getStore($slug);
        $volta = trim((string) ($_GET['volta'] ?? ''));
        $prefixo = 'loja/' . $store['slug'];
        $voltaValida = $volta !== ''
            && ($volta === $prefixo || strpos($volta, $prefixo . '/') === 0 || strpos($volta, $prefixo . '?') === 0)
            && strpos($volta, '..') === false
            && preg_match('#^[A-Za-z0-9/_\-?=&%.\#]+$#', $volta) === 1;
        if ($voltaValida) {
            $_SESSION['_after_login'] = base_url($volta);
        }
        $motivos = [
            'favoritos' => 'Entre na sua conta para salvar produtos nos favoritos.',
            'avaliar' => 'Entre com a conta usada na compra para avaliar o produto.',
        ];
        $motivo = (string) ($_GET['motivo'] ?? '');
        if (isset($motivos[$motivo])) {
            $_SESSION['_info'] = $motivos[$motivo];
        }
        if ($this->currentUser() !== null) {
            $destino = (string) ($_SESSION['_after_login'] ?? '');
            unset($_SESSION['_after_login']);
            redirect($destino !== '' && strpos($destino, rtrim(base_url(), '/') . '/') === 0 ? $destino : base_url($prefixo));
        }
        $modo = ($_GET['modo'] ?? '') === 'cadastro' ? 'cadastro' : 'login';
        $this->render('store/entrar', [
            'store' => $store,
            'modo' => $modo,
            'hide_whatsapp_fab' => true,
            'title' => ($modo === 'cadastro' ? 'Criar conta' : 'Entrar') . ' — ' . $store['name'],
            'meta_robots' => 'noindex',
        ]);
    }

    /**
     * 404 dentro da loja: mesmo cabeçalho, mesmas cores e um caminho de volta
     * para a vitrine, em vez de uma linha de texto sem estilo.
     *
     * @param list<array> $sugestoes produtos para mostrar abaixo da mensagem
     */
    private function naoEncontrado(array $store, string $titulo, string $mensagem, array $sugestoes = []): void
    {
        http_response_code(404);
        $this->render('store/nao_encontrado', [
            'store' => $store,
            'nf_titulo' => $titulo,
            'nf_mensagem' => $mensagem,
            'products' => $sugestoes,
            'title' => $titulo . ' — ' . $store['name'],
            'meta_robots' => 'noindex',
        ]);
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
            \App\Http\NotFound::pagina('Loja não encontrada', 'Confira o endereço. A loja pode ter mudado de nome ou saído do ar.');
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
