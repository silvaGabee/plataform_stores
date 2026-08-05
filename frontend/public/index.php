<?php
// Evitar que Notices/Warnings do PHP gerem HTML na resposta da API
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    ini_set('display_errors', '0');
}
ob_start();

// Cookie de sessão endurecido. Precisa vir ANTES de session_start().
//   httponly  — JavaScript não lê o cookie, então um XSS não rouba a sessão.
//   samesite  — o navegador não manda o cookie em POST cross-site (defesa
//               parcial contra CSRF; a validação de token vem na Fase 2).
//   secure    — só sob HTTPS; em HTTP puro marcá-lo quebraria o login.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
]);
session_start();

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = preg_replace('#/public/index\.php$#', '/public', $scriptName);
if ($basePath === $scriptName) {
    $basePath = preg_replace('#/index\.php$#', '', $scriptName) ?: '/plataform_stores';
}

$root = dirname(__DIR__, 2);
require $root . '/backend/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

$configApp = require PLATAFORM_BACKEND . '/config/app.php';
date_default_timezone_set($configApp['timezone'] ?? 'UTC');

$appDebug = !empty($configApp['debug']);
// Em rota de API, display_errors fica desligado mesmo em debug: um Warning
// impresso antes do JSON quebra o parse no cliente. O erro continua indo para
// o log e, em debug, para o campo "debug" da resposta.
$isApiUri = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
ini_set('display_errors', $appDebug && !$isApiUri ? '1' : '0');
error_reporting($appDebug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use App\Router;

/**
 * Resposta de erro única para qualquer exceção não tratada.
 *
 * A mensagem da exceção NUNCA vai para o cliente fora de debug: $e->getMessage()
 * de um PDOException carrega nome de tabela, de coluna e trecho de SQL. O que o
 * usuário recebe é o id que log_exception() gravou junto do stack trace.
 */
$renderError = static function (\Throwable $e, bool $isApi) use ($appDebug): void {
    $ref = log_exception($e, [
        'path'   => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ]);
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['error' => 'Erro interno do servidor.', 'ref' => $ref];
        if ($appDebug) {
            $payload['debug'] = $e->getMessage();
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);

        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Erro interno</h1><p>Não foi possível concluir a operação.</p>'
        . '<p>Referência: <code>' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</code></p>';
    if ($appDebug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
};

$router = new Router($basePath);
$path = $router->getPath();
$method = $router->getMethod();
$isApiRequest = strpos($path, '/api/') === 0;

// Servir uploads (fotos de produtos, etc.)
if (preg_match('#^/uploads/(.+)$#', $path, $m)) {
    $file = __DIR__ . '/uploads/' . $m[1];
    if (file_exists($file) && is_file($file) && strpos(realpath($file), realpath(__DIR__ . '/uploads')) === 0) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($file);
        exit;
    }
}

// Servir arquivos estáticos
if (preg_match('#^/assets/(.+)$#', $path, $m)) {
    $assetPath = $m[1];
    $file = __DIR__ . '/assets/' . $assetPath;
    // realpath() contém o caminho dentro de assets/ — sem isto, um path com
    // ".." escapado pelo servidor web serviria qualquer arquivo do disco.
    $real = $file !== '' ? realpath($file) : false;
    $assetsRoot = realpath(__DIR__ . '/assets');
    if ($real !== false && $assetsRoot !== false && strpos($real, $assetsRoot) === 0 && is_file($real)) {
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($real);
        exit;
    }
}

/**
 * Despacha a primeira rota que casar.
 *
 * O Guard roda ANTES do controller e é quem valida CSRF e permissão. Rota que
 * não declare seu requisito faz o Guard lançar — endpoint novo nasce fechado,
 * em vez de aberto por esquecimento.
 */
$despachar = static function (array $routes, bool $isApi) use ($router, $renderError): bool {
    foreach ($routes as $pattern => $handler) {
        $params = $router->match($pattern);
        if ($params === null) {
            continue;
        }
        try {
            App\Http\Guard::autorizar($pattern, $handler, Router::namedParams($pattern, $params), $isApi);
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action(...$params);
        } catch (\Throwable $e) {
            // As rotas web não tinham try/catch: qualquer exceção virava stack
            // trace cru na tela do visitante.
            $renderError($e, $isApi);
        }

        return true;
    }

    return false;
};

if ($despachar(require PLATAFORM_BACKEND . '/routes/api.php', true)) {
    exit;
}
if ($despachar(require PLATAFORM_BACKEND . '/routes/web.php', false)) {
    exit;
}

if ($isApiRequest) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Rota não encontrada', 'path' => $path], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>404 - Página não encontrada</h1><p>' . htmlspecialchars($path) . '</p>';
}
