<?php

namespace App\Controllers;

use App\Services\AiAssistantService;

class AiController extends Controller
{
    private const MSG_INDISPONIVEL = 'Assistente temporariamente indisponível';

    /** POST /api/loja/{slug}/ai/chat — corpo: { "pergunta": "..." } */
    public function chat(string $slug): void
    {
        $this->runChat($slug, $this->getJsonInput());
    }

    /**
     * POST /api/ai/chat — corpo: { "slug": "loja", "pergunta": "..." }
     * O slug identifica a loja; o acesso segue as mesmas regras do painel.
     */
    public function chatGlobal(): void
    {
        $input = $this->getJsonInput();
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $this->json(['error' => 'Informe o slug da loja no JSON (campo slug).'], 400);
        }
        $this->runChat($slug, $input);
    }

    private function runChat(string $slug, array $input): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        // POST /api/ai/chat recebe o slug no corpo, então o Guard não teve como
        // resolver a loja pela URL — a permissão é conferida aqui.
        $this->requirePermission('store.ai.use', $storeId);
        // Cada pergunta é uma chamada paga à OpenRouter: 30 por usuário a cada
        // 10 minutos evita que um laço no navegador queime o crédito da conta.
        if (!\App\Auth\RateLimiter::tentar('ai', (string) ($_SESSION['logged_user_id'] ?? ''), 30, 600)) {
            $this->json(['error' => 'Muitas perguntas em pouco tempo. Aguarde um instante.'], 429);
        }
        $pergunta = isset($input['pergunta']) ? trim((string) $input['pergunta']) : '';
        if ($pergunta === '') {
            $this->json(['error' => 'Informe a pergunta.'], 400);
        }
        $max = AiAssistantService::maxPerguntaLength();
        $len = function_exists('mb_strlen') ? mb_strlen($pergunta, 'UTF-8') : strlen($pergunta);
        if ($len > $max) {
            $this->json(['error' => 'Pergunta muito longa. Limite de ' . $max . ' caracteres.'], 400);
        }
        $service = new AiAssistantService();
        $resposta = $service->responderPerguntaLoja($storeId, $pergunta);
        if ($resposta === null) {
            $payload = ['resposta' => self::MSG_INDISPONIVEL];
            $cfg = @include PLATAFORM_BACKEND . '/config/app.php';
            if (is_array($cfg) && !empty($cfg['debug'])) {
                $fail = $service->getLastOpenRouterFailure();
                if ($fail !== '') {
                    $payload['detalhe_ia'] = $fail;
                }
            }
            $this->json($payload, 200);
            return;
        }
        $this->json(['resposta' => $resposta]);
    }

    public function descricaoProduto(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $nome = trim((string) ($input['nome'] ?? ''));
        if ($nome === '') {
            $this->json(['error' => 'Informe o nome do produto.'], 400);
        }
        $service = new AiAssistantService();
        $out = $service->gerarDescricaoProduto($storeId, $input);
        if ($out === null || ($out['descricao_curta'] === '' && $out['descricao_completa'] === '')) {
            $this->json(['resposta' => self::MSG_INDISPONIVEL]);
            return;
        }
        $this->json([
            'descricao_curta' => $out['descricao_curta'],
            'descricao_completa' => $out['descricao_completa'],
        ]);
    }

    /** GET /api/loja/{slug}/ai/reports/last30 */
    public function last30Report(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $service = new \App\Services\ReportService();
        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-29 days'));
        $revenue = $service->storeRevenueByType($storeId, $start, $end);
        $sales = $service->salesByPeriod($storeId, $start, $end);
        $this->json([
            'period_start' => $start,
            'period_end' => $end,
            'revenue' => $revenue['total'],
            'revenue_fisico' => $revenue['revenue_fisico'],
            'revenue_online' => $revenue['revenue_online'],
            'daily' => $sales,
        ]);
    }

    /** GET /api/loja/{slug}/ai/snapshot — retorna snapshot sanitizado da loja */
    public function storeSnapshot(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $pdo = \App\Database\Database::getConnection();

        // Produtos (limitados)
        $prodStmt = $pdo->prepare('SELECT id, name, sale_price, stock_quantity, min_stock FROM products WHERE store_id = ? ORDER BY name LIMIT 200');
        $prodStmt->execute([$storeId]);
        $products = $prodStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Equipe da loja (sem senhas). O cargo vem de store_members: users
        // deixou de guardar store_id/user_type.
        $userStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, m.role AS user_type
               FROM store_members m JOIN users u ON u.id = m.user_id
              WHERE m.store_id = ? ORDER BY u.name LIMIT 200'
        );
        $userStmt->execute([$storeId]);
        $users = $userStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Pedidos recentes (sem dados sensíveis)
        $orderStmt = $pdo->prepare("SELECT id, customer_id, status, total, created_at FROM orders WHERE store_id = ? ORDER BY created_at DESC LIMIT 200");
        $orderStmt->execute([$storeId]);
        $orders = $orderStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Itens de pedido (para os pedidos retornados)
        $orderIds = array_map(function($o){ return (int)$o['id']; }, $orders);
        $items = [];
        if (!empty($orderIds)) {
            $in = implode(',', array_fill(0, count($orderIds), '?'));
            $itStmt = $pdo->prepare("SELECT oi.order_id, oi.product_id, oi.quantity, oi.price, p.name as product_name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in)");
            $itStmt->execute($orderIds);
            $items = $itStmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Pagamentos (sem campos sensíveis)
        $payStmt = $pdo->prepare('SELECT id, order_id, method, status, amount, card_brand, card_last4 FROM payments WHERE store_id = ? ORDER BY created_at DESC LIMIT 200');
        $payStmt->execute([$storeId]);
        $payments = $payStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'store_id' => $storeId,
            'products' => $products,
            'users' => $users,
            'orders' => $orders,
            'order_items' => $items,
            'payments' => $payments,
        ]);
    }
}
