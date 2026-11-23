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
        $podeVerBi = \App\Auth\Permissions::can('store.bi.read', $storeId);
        // A IA pode levar até ~2 minutos. Com a sessão aberta, o PHP trava
        // todas as outras requisições do mesmo navegador (outra aba, PDV) até
        // ela responder. Daqui em diante só se LÊ a sessão.
        session_write_close();
        // O faturamento só entra no contexto de quem pode ver o BI. Tirar o dado
        // é o que protege: uma instrução no prompt o modelo poderia não seguir.
        $resposta = $service->responderPerguntaLoja($storeId, $pergunta, $podeVerBi, $slug);
        if ($resposta === null) {
            // O motivo técnico (provedor, código HTTP) não vai para o chat:
            // fica no log do PHP e, com debug ligado, no console do navegador.
            $fail = $service->getLastOpenRouterFailure();
            if ($fail !== '') {
                error_log('[assistente IA] ' . $fail);
            }
            $payload = ['resposta' => $service->ultimaFalhaFoiLimite()
                ? 'Muitas perguntas seguidas. Aguarde cerca de um minuto e pergunte de novo.'
                : self::MSG_INDISPONIVEL];
            $cfg = @include PLATAFORM_BACKEND . '/config/app.php';
            if (is_array($cfg) && !empty($cfg['debug']) && $fail !== '') {
                $payload['detalhe_ia'] = $fail;
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
        // Também é uma chamada paga: mesmo limite por pessoa do chat, contado à parte.
        if (!\App\Auth\RateLimiter::tentar('ai-descricao', (string) ($_SESSION['logged_user_id'] ?? ''), 30, 600)) {
            $this->json(['error' => 'Muitos pedidos em pouco tempo. Aguarde um instante.'], 429);
        }
        $input = $this->getJsonInput();
        foreach (['nome' => 150, 'categoria' => 100, 'caracteristicas' => 1000] as $campo => $limite) {
            if (isset($input[$campo]) && !is_scalar($input[$campo])) {
                $this->json(['error' => 'Campo inválido: ' . $campo . '.'], 400);
            }
            if (mb_strlen(trim((string) ($input[$campo] ?? '')), 'UTF-8') > $limite) {
                $this->json(['error' => 'O campo ' . $campo . ' passa de ' . $limite . ' caracteres.'], 400);
            }
        }
        $nome = trim((string) ($input['nome'] ?? ''));
        if ($nome === '') {
            $this->json(['error' => 'Informe o nome do produto.'], 400);
        }
        session_write_close();
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
