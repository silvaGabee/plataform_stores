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
        // O faturamento só entra no contexto de quem pode ver o BI. Tirar o dado
        // é o que protege: uma instrução no prompt o modelo poderia não seguir.
        $resposta = $service->responderPerguntaLoja($storeId, $pergunta, \App\Auth\Permissions::can('store.bi.read', $storeId));
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
}
