<?php

namespace App\Services;

use App\Repositories\StorePixConfigRepository;
use App\Support\QrCode;

class PixService
{
    /** O checkout do cliente espera por isto: falhar rápido é melhor que pendurar. */
    private const CONNECT_TIMEOUT_SEC = 3;
    private const TIMEOUT_SEC = 8;

    public function __construct(
        private StorePixConfigRepository $pixConfigRepo
    ) {}

    /**
     * Gera QR Code PIX.
     * Se RAPIDAPI_KEY e chave PIX estiverem configurados, usa pix-qr-code1.p.rapidapi.com.
     * Sem ela, o QR é desenhado aqui mesmo, a partir do BR Code — sem mandar a
     * chave PIX do lojista para serviço nenhum (ver App\Support\QrCode).
     */
    public function generateQrCode(int $storeId, float $amount, string $description = 'Pagamento'): ?string
    {
        return $this->generate($storeId, $amount, $description)['qr_code'];
    }

    /**
     * Dados de pagamento PIX: imagem do QR e copia e cola, da MESMA origem.
     *
     * Vinham de chamadas separadas — a imagem da API e o texto montado aqui.
     * Como cada uma formata a referência à sua maneira, o cliente podia
     * escanear um payload e copiar outro. Agora, quando a API responde, os dois
     * saem da resposta dela; quando não, os dois são gerados localmente.
     *
     * @return array{qr_code: ?string, copia_cola: ?string, origem: string}
     */
    public function generate(int $storeId, float $amount, string $description = 'Pagamento'): array
    {
        $vazio = ['qr_code' => null, 'copia_cola' => null, 'origem' => 'sem-chave-pix'];

        $config = $this->pixConfigRepo->findByStore($storeId);
        $pixKey = $config['pix_key'] ?? null;
        if (empty($pixKey)) {
            return $vazio;
        }

        $rapidApiKey = config('app.rapidapi_key');
        if (!empty($rapidApiKey)) {
            $resposta = $this->chamarPixRapidApi($rapidApiKey, [
                'key_type' => $this->mapKeyType($config['pix_key_type'] ?? 'aleatoria'),
                'key' => $pixKey,
                'name' => $config['merchant_name'] ?: 'Loja',
                'city' => $config['merchant_city'] ?: 'Sao Paulo',
                'amount' => number_format($amount, 2, '.', ''),
                'reference' => $description,
            ]);
            if ($resposta !== null) {
                $imagem = $this->normalizarImagem($this->extractPixFromResponse($resposta));
                $brCode = $this->brCodeFromResponse($resposta);
                if ($imagem !== null || $brCode !== null) {
                    return [
                        'qr_code' => $imagem,
                        // Se a API não devolver o texto, monta localmente — o
                        // BR Code é determinístico a partir da mesma configuração.
                        'copia_cola' => $brCode ?? $this->buildCopyPaste($storeId, $amount, $description),
                        'origem' => 'rapidapi',
                    ];
                }
            }
        }

        // Sem chave, ou API fora do ar: tudo local. A chave PIX do lojista não
        // sai do servidor neste caminho.
        $payload = $this->buildCopyPaste($storeId, $amount, $description);
        if ($payload === null) {
            return $vazio;
        }

        return [
            'qr_code' => QrCode::toDataUri($payload),
            'copia_cola' => $payload,
            'origem' => 'local',
        ];
    }

    /** Transforma o que a API devolveu numa src utilizável por <img>. */
    private function normalizarImagem(?string $img): ?string
    {
        if ($img === null || $img === '') {
            return null;
        }
        if (strpos($img, 'data:') === 0 || strpos($img, 'http') === 0) {
            return $img;
        }
        // Base64 puro, sem o prefixo do data URI.
        return strlen($img) > 50 ? 'data:image/png;base64,' . $img : null;
    }

    /**
     * Monta o payload PIX (BR Code) estático/dinâmico para QR Code.
     * Formato EMV: tag (2) + length (2) + value.
     */
    public function buildPixPayload(string $pixKey, string $merchantName, string $merchantCity, float $amount, string $txId): ?string
    {
        $merchantName = mb_substr($merchantName, 0, 25);
        $merchantCity = mb_substr($merchantCity, 0, 15);
        $txId = mb_substr($txId, 0, 25);
        if ($txId === '') {
            $txId = '***';
        }

        $gui = 'br.gov.bcb.pix';
        $keyLen = strlen($pixKey);
        $merchantAccount = '0014' . $gui . '01' . str_pad((string) $keyLen, 2, '0', STR_PAD_LEFT) . $pixKey;
        $amountStr = number_format($amount, 2, '.', '');
        $txIdLen = strlen($txId);
        $addData = '05' . str_pad((string) $txIdLen, 2, '0', STR_PAD_LEFT) . $txId;
        $payload = '00020126' . str_pad((string) strlen($merchantAccount), 2, '0', STR_PAD_LEFT) . $merchantAccount
            . '52040000530398654' . str_pad((string) strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr
            . '5802BR59' . str_pad((string) strlen($merchantName), 2, '0', STR_PAD_LEFT) . $merchantName
            . '60' . str_pad((string) strlen($merchantCity), 2, '0', STR_PAD_LEFT) . $merchantCity
            . '62' . str_pad((string) strlen($addData), 2, '0', STR_PAD_LEFT) . $addData
            . '6304';

        $crc = $this->crc16Ccitt($payload);
        return $payload . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private function crc16Ccitt(string $str): int
    {
        $crc = 0xFFFF;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($str[$i]) & 0xFF) << 8;
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc & 0xFFFF;
    }

    private function mapKeyType(string $type): string
    {
        $map = ['cpf' => 'cpf', 'cnpj' => 'cnpj', 'email' => 'email', 'telefone' => 'telefone', 'aleatoria' => 'random'];
        return $map[$type] ?? 'random';
    }

    /**
     * Monta o "PIX copia e cola" (BR Code) da loja para este valor.
     *
     * Substituiu a chamada a `api.qrserver.com`, que recebia este mesmo payload
     * no query string — ou seja, a CHAVE PIX do lojista e o valor de cada venda
     * saíam para um serviço gratuito de terceiros e ficavam no log de acesso
     * deles, além de tornar o pagamento dependente de um site externo estar no ar.
     *
     * O copia e cola é aceito por todos os aplicativos de banco e é gerado
     * inteiramente aqui. Gerar a IMAGEM do QR exigiria um codificador próprio
     * (Reed-Solomon, máscaras) que não teríamos como verificar sem um leitor —
     * e um QR sutilmente errado é um pagamento que falha no caixa. Com chave
     * RapidAPI configurada, a imagem continua vindo de lá.
     */
    public function buildCopyPaste(int $storeId, float $amount, string $description = 'Pagamento'): ?string
    {
        $config = $this->pixConfigRepo->findByStore($storeId);
        $pixKey = $config['pix_key'] ?? null;
        if (empty($pixKey)) {
            return null;
        }

        return $this->buildPixPayload(
            $pixKey,
            $config['merchant_name'] ?: 'Loja',
            $config['merchant_city'] ?: 'Sao Paulo',
            $amount,
            substr(preg_replace('/[^a-zA-Z0-9]/', '', $description), 0, 25)
        );
    }

    /**
     * Chama pix-qr-code1.p.rapidapi.com/generate e devolve o JSON cru.
     *
     * Devolver a resposta inteira, em vez de já extrair a imagem, é o que
     * permite pegar o QR e o BR Code da MESMA chamada. A versão anterior
     * decidia aqui dentro e mascarou o defeito: a resposta trazia a imagem em
     * `qrcode_base64`, a extração casava com `code` (texto), e o método
     * devolvia null — a API respondia 200 com o QR pronto e era ignorada.
     *
     * @return array<string, mixed>|null
     */
    private function chamarPixRapidApi(string $apiKey, array $body): ?array
    {
        $ch = curl_init('https://pix-qr-code1.p.rapidapi.com/generate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-rapidapi-key: ' . $apiKey,
                'x-rapidapi-host: pix-qr-code1.p.rapidapi.com',
            ],
            // Sem timeout, uma API lenta pendurava o checkout do cliente até o
            // max_execution_time do PHP. Falhar rápido é melhor: quem chama
            // cai no gerador local, que não depende de rede.
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SEC,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
        ]);
        $resposta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($errno || $codigo !== 200 || !is_string($resposta) || $resposta === '') {
            log_message('warning', 'PIX: RapidAPI indisponível, usando gerador local', [
                'http' => $codigo,
                'curl' => $errno ? $erro : null,
            ]);

            return null;
        }
        $dados = json_decode($resposta, true);

        return is_array($dados) ? $dados : null;
    }

    /**
     * Extrai a IMAGEM do QR da resposta da API.
     *
     * A lista de chaves não incluía `qrcode_base64`, que é justamente a que a
     * pix-qr-code1 usa para a imagem. A busca acabava casando com `code` — o
     * texto do BR Code, não uma imagem — e o método chamador, ao ver que
     * começava com "00020126", devolvia null. Resultado: a API respondia 200
     * com o QR pronto e o sistema ignorava, caindo no gerador local.
     *
     * `qrcode_base64` vem primeiro na lista por ser a chave real desta API.
     */
    private function extractPixFromResponse(array $data): ?string
    {
        $chavesDeImagem = [
            'qrcode_base64', 'qr_code_base64', 'qrCodeBase64',
            'qr_code', 'qrCode', 'qrcode', 'image', 'url',
        ];
        foreach ([$data, $data['data'] ?? []] as $nivel) {
            if (!is_array($nivel)) {
                continue;
            }
            foreach ($chavesDeImagem as $chave) {
                $valor = $nivel[$chave] ?? null;
                // Só serve o que for imagem: data URI, URL, ou base64 puro.
                // O BR Code em texto é tratado em brCodeFromResponse().
                if (is_string($valor) && $valor !== '' && strpos($valor, '00020126') !== 0) {
                    return $valor;
                }
            }
        }

        return null;
    }

    /** Extrai o BR Code (copia e cola) da resposta da API. */
    private function brCodeFromResponse(array $data): ?string
    {
        $chaves = ['code', 'brcode', 'payload', 'emv', 'pix_copy_paste', 'copy_paste', 'qr_code_text'];
        foreach ([$data, $data['data'] ?? []] as $nivel) {
            if (!is_array($nivel)) {
                continue;
            }
            foreach ($chaves as $chave) {
                $valor = $nivel[$chave] ?? null;
                if (is_string($valor) && strpos($valor, '00020126') === 0) {
                    return $valor;
                }
            }
            // Última tentativa: qualquer campo que pareça um BR Code.
            foreach ($nivel as $valor) {
                if (is_string($valor) && strpos($valor, '00020126') === 0) {
                    return $valor;
                }
            }
        }

        return null;
    }
}
