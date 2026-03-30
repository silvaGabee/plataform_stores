<?php

namespace App\Services;

use App\Repositories\StorePixConfigRepository;

class PixService
{
    public function __construct(
        private StorePixConfigRepository $pixConfigRepo
    ) {}

    /**
     * Gera QR Code PIX.
     * Se RAPIDAPI_KEY e chave PIX estiverem configurados, usa pix-qr-code1.p.rapidapi.com.
     * Caso contrário, usa API gratuita (api.qrserver.com).
     */
    public function generateQrCode(int $storeId, float $amount, string $description = 'Pagamento'): ?string
    {
        $config = $this->pixConfigRepo->findByStore($storeId);
        $pixKey = $config['pix_key'] ?? null;
        $pixKeyType = $config['pix_key_type'] ?? 'aleatoria';
        $merchantName = $config['merchant_name'] ?? '';
        $merchantCity = $config['merchant_city'] ?? '';

        $rapidApiKey = config('app.rapidapi_key');
        if (!empty($rapidApiKey) && !empty($pixKey)) {
            $result = $this->generateViaPixRapidApi($rapidApiKey, [
                'key_type' => $this->mapKeyType($pixKeyType),
                'key' => $pixKey,
                'name' => $merchantName ?: 'Loja',
                'city' => $merchantCity ?: 'São Paulo',
                'amount' => number_format($amount, 2, '.', ''),
                'reference' => $description,
            ]);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback: gera payload PIX (BR Code) e QR via API gratuita
        if (!empty($pixKey)) {
            $payload = $this->buildPixPayload($pixKey, $merchantName ?: 'Loja', $merchantCity ?: 'Sao Paulo', $amount, substr(preg_replace('/[^a-zA-Z0-9]/', '', $description), 0, 25));
            if ($payload !== null) {
                return $this->generateViaFreeApi($payload);
            }
        }
        return null;
    }

    /**
     * Monta o payload PIX (BR Code) estático/dinâmico para QR Code.
     * Formato EMV: tag (2) + length (2) + value.
     */
    private function buildPixPayload(string $pixKey, string $merchantName, string $merchantCity, float $amount, string $txId): ?string
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

    private function generateViaFreeApi(string $text): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($text);
    }

    /**
     * API: pix-qr-code1.p.rapidapi.com/generate
     */
    private function generateViaPixRapidApi(string $apiKey, array $body): ?string
    {
        $url = 'https://pix-qr-code1.p.rapidapi.com/generate';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-rapidapi-key: ' . $apiKey,
                'x-rapidapi-host: pix-qr-code1.p.rapidapi.com',
            ],
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno || $code !== 200 || !$response) {
            return null;
        }
        $data = json_decode($response, true);
        if (!$data || !is_array($data)) {
            return null;
        }

        $img = $this->extractPixFromResponse($data);
        if ($img === null) {
            return null;
        }

        if (strpos($img, 'data:') === 0) {
            return $img;
        }
        if (strpos($img, 'http') === 0) {
            return $img;
        }
        if (strpos($img, '00020126') === 0) {
            return $this->generateViaFreeApi($img);
        }
        if (strlen($img) > 50) {
            return 'data:image/png;base64,' . $img;
        }
        return null;
    }

    /**
     * Extrai o payload PIX (EMV) ou imagem do QR da resposta da API.
     * O PIX válido começa com 00020126 (br.gov.bcb.pix).
     */
    private function extractPixFromResponse(array $data): ?string
    {
        $keys = ['brcode', 'payload', 'emv', 'pix_copy_paste', 'copy_paste', 'qr_code', 'qrCode', 'qrcode', 'qr_code_base64', 'image', 'url', 'result', 'code'];
        foreach ($keys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($keys as $key) {
                if (!empty($data['data'][$key]) && is_string($data['data'][$key])) {
                    return $data['data'][$key];
                }
            }
        }
        foreach ($data as $value) {
            if (is_string($value) && strpos($value, '00020126') === 0) {
                return $value;
            }
        }
        return null;
    }
}
