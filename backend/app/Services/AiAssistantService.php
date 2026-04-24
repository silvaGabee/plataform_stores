<?php

namespace App\Services;

use App\Database\Database;
use App\Repositories\StoreRepository;
use PDO;

class AiAssistantService
{
    private const MAX_PERGUNTA_CHARS = 2000;
    private const CURL_TIMEOUT_SEC = 90;

    /** Modelos gratuitos alternativos (só em `models`; principal em `model`). */
    private const OPENROUTER_DEFAULT_FREE_FALLBACKS = [
        'meta-llama/llama-3.2-3b-instruct:free',
        'qwen/qwen3-next-80b-a3b-instruct:free',
        'google/gemma-3-4b-it:free',
    ];

    private PDO $pdo;
    private ReportService $reports;
    private StoreRepository $stores;

    /** Última falha na chamada OpenRouter (para diagnóstico com debug ativo). */
    private string $lastOpenRouterFailure = '';

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->reports = new ReportService();
        $this->stores = new StoreRepository();
    }

    public static function maxPerguntaLength(): int
    {
        return self::MAX_PERGUNTA_CHARS;
    }

    public function getLastOpenRouterFailure(): string
    {
        return $this->lastOpenRouterFailure;
    }

    /**
     * IDs para o parâmetro `models` da OpenRouter (só fallbacks; o principal vai em `model`).
     *
     * @see https://openrouter.ai/docs/guides/routing/model-fallbacks
     */
    private function openRouterFallbackModelIds(string $primaryModel): array
    {
        $off = trim((string) (getenv('OPENROUTER_DISABLE_MODEL_FALLBACK') ?: ($_ENV['OPENROUTER_DISABLE_MODEL_FALLBACK'] ?? '')));
        if ($off === '1' || strcasecmp($off, 'true') === 0 || strcasecmp($off, 'yes') === 0) {
            return [];
        }
        $raw = trim((string) (getenv('OPENROUTER_MODEL_FALLBACKS') ?: ($_ENV['OPENROUTER_MODEL_FALLBACKS'] ?? '')));
        $candidates = [];
        if ($raw !== '') {
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        } else {
            $candidates = self::OPENROUTER_DEFAULT_FREE_FALLBACKS;
        }
        $out = [];
        foreach ($candidates as $id) {
            if (strcasecmp($id, $primaryModel) === 0) {
                continue;
            }
            $out[] = $id;
        }
        return array_values(array_unique($out));
    }

    /** @param array<string, mixed>|mixed $err */
    private function formatOpenRouterErrorPayload($err): string
    {
        if (!is_array($err)) {
            return (string) $err;
        }
        $parts = [];
        if (isset($err['message']) && (string) $err['message'] !== '') {
            $parts[] = (string) $err['message'];
        }
        if (isset($err['code'])) {
            $parts[] = 'código ' . $err['code'];
        }
        if (!empty($err['metadata']) && is_array($err['metadata'])) {
            $meta = json_encode($err['metadata'], JSON_UNESCAPED_UNICODE);
            if (strlen($meta) > 500) {
                $meta = substr($meta, 0, 500) . '…';
            }
            $parts[] = $meta;
        }
        if ($parts === []) {
            return json_encode($err, JSON_UNESCAPED_UNICODE);
        }
        return implode(' — ', $parts);
    }

    /**
     * Alguns modelos gratuitos (ex.: Google Gemma via OpenRouter) não aceitam role "system" e retornam
     * "Developer instruction is not enabled". Junta o system na primeira mensagem user.
     *
     * @param list<array{role: string, content: string}> $messages
     * @return list<array{role: string, content: string}>
     */
    private function openRouterCompatMessages(array $messages): array
    {
        $systemChunks = [];
        $rest = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? '');
            if ($role === 'system') {
                $systemChunks[] = trim((string) ($m['content'] ?? ''));
                continue;
            }
            $rest[] = $m;
        }
        $systemChunks = array_values(array_filter($systemChunks, static function ($s) {
            return $s !== '';
        }));
        if ($systemChunks === []) {
            return $messages;
        }
        $sysBlock = implode("\n\n", $systemChunks);
        $header = "[Instruções do assistente — siga antes de responder]\n" . $sysBlock . "\n[— fim das instruções —]\n\n";
        if ($rest !== [] && (string) ($rest[0]['role'] ?? '') === 'user') {
            $rest[0] = [
                'role' => 'user',
                'content' => $header . (string) ($rest[0]['content'] ?? ''),
            ];

            return $rest;
        }
        array_unshift($rest, [
            'role' => 'user',
            'content' => $header . 'Responda de acordo com as instruções acima.',
        ]);

        return $rest;
    }

    public function responderPerguntaLoja(int $storeId, string $pergunta): ?string
    {
        $pergunta = trim($pergunta);
        if ($pergunta === '') {
            return null;
        }
        $contexto = $this->montarContextoLoja($storeId);
        $regras = $this->carregarRegrasIA();
        $system = $regras . "\n\nVocê é um assistente de gestão para o dono de uma loja. Seja direto: responda apenas ao que ele perguntou.";
        $user = "A seguir há um SNAPSHOT OPCIONAL com métricas atuais da loja (vendas, pedidos, estoque, equipe).\n\n"
            . "USE esse snapshot SOMENTE se a pergunta for sobre desempenho da loja, números, vendas, pedidos, estoque, produtos mais vendidos ou situação operacional.\n\n"
            . "Se a pergunta for geral (dicas de produto, lucro, marketing, ideias, conceitos, “o que vender”, etc.), responda só a isso, de forma útil, SEM abrir com resumo de vendas nem citar métricas do snapshot. Não invente dados da loja nesses casos.\n\n"
            . "--- SNAPSHOT DOS DADOS DA LOJA ---\n"
            . $contexto
            . "\n--- FIM DO SNAPSHOT ---\n\n"
            . "PERGUNTA DO DONO:\n"
            . $pergunta;
        return $this->chamarOpenRouter([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]);
    }

    /**
     * @param array{nome?: string, categoria?: string, caracteristicas?: string} $dadosProduto
     * @return array{descricao_curta: string, descricao_completa: string}|null
     */
    public function gerarDescricaoProduto(int $storeId, array $dadosProduto): ?array
    {
        $nome = trim((string) ($dadosProduto['nome'] ?? ''));
        if ($nome === '') {
            return null;
        }
        $cat = trim((string) ($dadosProduto['categoria'] ?? ''));
        $car = trim((string) ($dadosProduto['caracteristicas'] ?? ''));
        $store = $this->stores->find($storeId);
        $nomeLoja = $store ? trim((string) ($store['name'] ?? '')) : '';
        $regras = $this->carregarRegrasIA();
        $system = $regras . "\n\nVocê gera textos de vitrine para produtos. Responda APENAS com um JSON válido (sem markdown), neste formato exato:\n{\"descricao_curta\":\"texto curto até 2 frases\",\"descricao_completa\":\"parágrafo mais detalhado\"}";
        $user = "Loja (contexto de tom, sem citar se não fizer sentido): {$nomeLoja}\nProduto: {$nome}\nCategoria do produto: {$cat}\nCaracterísticas: {$car}";
        $raw = $this->chamarOpenRouter([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['descricao_curta'], $decoded['descricao_completa'])) {
            return [
                'descricao_curta' => (string) $decoded['descricao_curta'],
                'descricao_completa' => (string) $decoded['descricao_completa'],
            ];
        }
        return [
            'descricao_curta' => '',
            'descricao_completa' => $raw,
        ];
    }

    public function montarContextoLoja(int $storeId): string
    {
        $store = $this->stores->find($storeId);
        if (!$store) {
            return 'Loja não encontrada.';
        }
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $hojeRev = $this->reports->storeRevenueByType($storeId, $today, $today);
        $semRev = $this->reports->storeRevenueByType($storeId, $weekStart, $today);
        $topSem = $this->reports->topProducts($storeId, $weekStart, $today, 5);
        $low = $this->reports->lowStockProducts($storeId);
        $pendentes = $this->contarPedidosPendentes($storeId);
        $funcionarios = $this->contarFuncionarios($storeId);

        $lines = [];
        $lines[] = 'Loja: ' . ($store['name'] ?? '');
        $lines[] = 'Categoria: ' . (string) ($store['category'] ?? '(não informada)');
        $lines[] = 'Vendas hoje (pedidos pagos): ' . $this->fmtBrl($hojeRev['total']);
        $lines[] = 'Vendas na semana (segunda a hoje, pedidos pagos): ' . $this->fmtBrl($semRev['total']);
        $lines[] = 'Produtos mais vendidos (semana): ' . $this->formatarTopProdutos($topSem);
        $lines[] = 'Pedidos pendentes (status pendente): ' . $pendentes;
        $lines[] = 'Produtos com estoque baixo: ' . count($low) . $this->nomesEstoqueBaixo($low);
        $lines[] = 'Funcionários e gerentes (usuários da equipe): ' . $funcionarios;

        return implode("\n", $lines);
    }

    private function contarPedidosPendentes(int $storeId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND status = 'pendente'");
        $stmt->execute([$storeId]);
        return (int) $stmt->fetchColumn();
    }

    private function contarFuncionarios(int $storeId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE store_id = ? AND user_type IN ('funcionario','gerente')"
        );
        $stmt->execute([$storeId]);
        return (int) $stmt->fetchColumn();
    }

    private function formatarTopProdutos(array $rows): string
    {
        if ($rows === []) {
            return 'nenhum dado no período';
        }
        $parts = [];
        foreach ($rows as $i => $row) {
            $name = (string) ($row['name'] ?? '');
            $qty = (float) ($row['total_qty'] ?? 0);
            if ($name !== '') {
                $parts[] = $name . ' (' . (int) $qty . ' un.)';
            }
        }
        return $parts === [] ? 'nenhum dado no período' : implode('; ', $parts);
    }

    private function nomesEstoqueBaixo(array $low): string
    {
        if ($low === []) {
            return '';
        }
        $names = array_slice(array_map(static function ($p) {
            return (string) ($p['name'] ?? '');
        }, $low), 0, 8);
        $names = array_filter($names);
        if ($names === []) {
            return '';
        }
        return ' — exemplos: ' . implode(', ', $names);
    }

    private function fmtBrl(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    private function carregarRegrasIA(): string
    {
        $path = PLATAFORM_BACKEND . '/config/regrasIA.txt';
        if (is_readable($path)) {
            $t = trim((string) file_get_contents($path));
            if ($t !== '') {
                return $t;
            }
        }
        return 'Responda apenas com base nos dados fornecidos. Não exponha detalhes técnicos do sistema nem dados de outras lojas.';
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     */
    private function chamarOpenRouter(array $messages): ?string
    {
        $this->lastOpenRouterFailure = '';
        $messages = $this->openRouterCompatMessages($messages);
        $apiKey = trim((string) (getenv('OPENROUTER_API_KEY') ?: ($_ENV['OPENROUTER_API_KEY'] ?? '')));
        $model = trim((string) (getenv('OPENROUTER_MODEL') ?: ($_ENV['OPENROUTER_MODEL'] ?? '')));
        if ($apiKey === '' || $model === '') {
            $this->lastOpenRouterFailure = 'OPENROUTER_API_KEY ou OPENROUTER_MODEL não foram lidos no servidor (confira o .env na raiz do projeto e reinicie o Apache).';
            return null;
        }
        $base = rtrim((string) (getenv('OPENROUTER_URL') ?: ($_ENV['OPENROUTER_URL'] ?? 'https://openrouter.ai/api/v1')), '/');
        // Aceita OPENROUTER_URL só com /api/v1 OU já com /chat/completions (evita URL duplicada e 404).
        $suffix = '/chat/completions';
        if (strlen($base) >= strlen($suffix) && substr_compare($base, $suffix, -strlen($suffix)) === 0) {
            $url = $base;
        } else {
            $url = $base . $suffix;
        }
        // Doc OpenRouter: `model` = principal; `models` = apenas fallbacks (não repetir o principal).
        $fallbackIds = $this->openRouterFallbackModelIds($model);
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        if ($fallbackIds !== []) {
            $payload['models'] = $fallbackIds;
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $this->lastOpenRouterFailure = 'Falha ao montar JSON da requisição.';
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            $this->lastOpenRouterFailure = 'cURL não está disponível no PHP (habilite a extensão curl).';
            return null;
        }
        // OpenRouter: HTTP-Referer e X-OpenRouter-Title (X-Title também é aceito). .env: OPENROUTER_SITE_URL, OPENROUTER_APP_NAME.
        $referer = trim((string) (getenv('OPENROUTER_SITE_URL') ?: ($_ENV['OPENROUTER_SITE_URL'] ?? '')));
        if ($referer === '') {
            $referer = 'http://localhost';
            $cfg = @include PLATAFORM_BACKEND . '/config/app.php';
            if (is_array($cfg) && !empty($cfg['url'])) {
                $referer = trim((string) $cfg['url']);
            }
        }
        $appTitle = trim((string) (getenv('OPENROUTER_APP_NAME') ?: ($_ENV['OPENROUTER_APP_NAME'] ?? '')));
        if ($appTitle === '') {
            $appTitle = 'Plataforma de Lojas';
        }
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . $referer,
            'X-OpenRouter-Title: ' . $appTitle,
        ];
        $ca = trim((string) (getenv('OPENROUTER_CURL_CAINFO') ?: ($_ENV['OPENROUTER_CURL_CAINFO'] ?? '')));
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT_SEC,
        ];
        if ($ca !== '' && is_readable($ca)) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            $this->lastOpenRouterFailure = 'cURL #' . $errno . ': ' . $curlErr;
            if ($errno === 60 || stripos($curlErr, 'SSL') !== false) {
                $this->lastOpenRouterFailure .= ' — No XAMPP/Windows: defina curl.cainfo no php.ini apontando para cacert.pem, ou use OPENROUTER_CURL_CAINFO no .env com o caminho completo do arquivo.';
            }
            return null;
        }
        if (!is_string($response) || $response === '') {
            $this->lastOpenRouterFailure = 'Resposta vazia (HTTP ' . $httpCode . ').';
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->lastOpenRouterFailure = 'Resposta não é JSON (HTTP ' . $httpCode . ').';
            return null;
        }
        if (!empty($data['error'])) {
            $this->lastOpenRouterFailure = $this->formatOpenRouterErrorPayload($data['error']);
            if ($httpCode > 0) {
                $this->lastOpenRouterFailure = '[HTTP ' . $httpCode . '] ' . $this->lastOpenRouterFailure;
            }
            if ($httpCode === 429) {
                $this->lastOpenRouterFailure .= ' — Fila/limite comum em modelos :free; aguarde, troque OPENROUTER_MODEL ou ajuste OPENROUTER_MODEL_FALLBACKS.';
            } elseif ($httpCode === 404) {
                $this->lastOpenRouterFailure .= ' — Confira OPENROUTER_URL (base https://openrouter.ai/api/v1 sem duplicar /chat/completions).';
            }
            return null;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->lastOpenRouterFailure = 'HTTP ' . $httpCode . ' da OpenRouter.';
            if ($httpCode === 429) {
                $this->lastOpenRouterFailure .= ' Limite ou fila no modelo gratuito — aguarde e tente novamente.';
            }
            return null;
        }
        $choice0 = $data['choices'][0] ?? null;
        if (is_array($choice0) && !empty($choice0['error'])) {
            $this->lastOpenRouterFailure = '[choice] ' . $this->formatOpenRouterErrorPayload($choice0['error']);
            return null;
        }
        $content = null;
        if (is_array($choice0) && isset($choice0['message']) && is_array($choice0['message'])) {
            $content = $choice0['message']['content'] ?? null;
        }
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        if (!is_string($content) || trim($content) === '') {
            $this->lastOpenRouterFailure = 'A API não retornou texto na resposta (modelo ou limite de tokens).';
            return null;
        }
        return trim($content);
    }
}
