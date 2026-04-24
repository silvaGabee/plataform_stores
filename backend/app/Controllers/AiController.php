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
        $this->requireStorePanelAccess($storeId);
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
}
