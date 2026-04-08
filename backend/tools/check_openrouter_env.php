<?php
/** Uso: php backend/tools/check_openrouter_env.php — não commita segredos; só comprimentos. */
require dirname(__DIR__) . '/bootstrap.php';

$key = trim((string) (getenv('OPENROUTER_API_KEY') ?: ($_ENV['OPENROUTER_API_KEY'] ?? '')));
$model = trim((string) (getenv('OPENROUTER_MODEL') ?: ($_ENV['OPENROUTER_MODEL'] ?? '')));
$url = trim((string) (getenv('OPENROUTER_URL') ?: ($_ENV['OPENROUTER_URL'] ?? '')));

echo 'OPENROUTER_API_KEY length: ' . strlen($key) . (strlen($key) > 0 ? ' (ok)' : ' (VAZIO)') . PHP_EOL;
echo 'OPENROUTER_MODEL: ' . ($model !== '' ? $model : '(VAZIO)') . PHP_EOL;
echo 'OPENROUTER_URL: ' . ($url !== '' ? $url : '(default)') . PHP_EOL;

if ($key === '' || $model === '') {
    exit(1);
}

$base = rtrim($url !== '' ? $url : 'https://openrouter.ai/api/v1', '/');
$suffix = '/chat/completions';
$endpoint = (strlen($base) >= strlen($suffix) && substr_compare($base, $suffix, -strlen($suffix)) === 0)
    ? $base
    : $base . $suffix;
$payload = json_encode([
    'model' => $model,
    'messages' => [['role' => 'user', 'content' => 'Responda apenas: OK']],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'HTTP-Referer: http://localhost',
        'X-OpenRouter-Title: TCC-Loja',
    ],
    CURLOPT_TIMEOUT => 60,
]);
$resp = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'cURL errno: ' . $errno . ($errno ? ' — ' . $err : '') . PHP_EOL;
echo 'HTTP: ' . $code . PHP_EOL;
$data = is_string($resp) ? json_decode($resp, true) : null;
if (is_array($data) && !empty($data['error'])) {
    $e = $data['error'];
    echo 'API error: ' . (is_array($e) ? ($e['message'] ?? json_encode($e)) : $e) . PHP_EOL;
    exit(2);
}
if (!is_array($data)) {
    echo 'Raw (first 300 chars): ' . substr((string) $resp, 0, 300) . PHP_EOL;
    exit(3);
}
$content = $data['choices'][0]['message']['content'] ?? null;
echo 'Reply preview: ' . substr(trim((string) $content), 0, 120) . PHP_EOL;
echo 'OK' . PHP_EOL;
