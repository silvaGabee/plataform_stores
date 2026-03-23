<?php
// Evitar que Notices/Warnings do PHP gerem HTML na resposta da API
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    ini_set('display_errors', '0');
}
ob_start();
session_start();

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = preg_replace('#/public/index\.php$#', '/public', $scriptName);
if ($basePath === $scriptName) {
    $basePath = preg_replace('#/index\.php$#', '', $scriptName) ?: '/plataform_stores';
}

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/app/Helpers/functions.php';

$configApp = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($configApp['timezone'] ?? 'UTC');

use App\Router;

$router = new Router($basePath);
$path = $router->getPath();
$method = $router->getMethod();

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
    if (file_exists($file) && is_file($file)) {
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
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($file);
        exit;
    }
}

// Rotas de API
$apiRoutes = require dirname(__DIR__) . '/routes/api.php';
foreach ($apiRoutes as $pattern => $handler) {
    $params = $router->match($pattern);
    if ($params !== null) {
        try {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action(...$params);
        } catch (\Throwable $e) {
            while (ob_get_level()) ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

// Rotas de páginas (web)
$webRoutes = require dirname(__DIR__) . '/routes/web.php';
foreach ($webRoutes as $pattern => $handler) {
    $params = $router->match($pattern);
    if ($params !== null) {
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action(...$params);
        exit;
    }
}

if (strpos($path, '/api/') === 0) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Rota não encontrada', 'path' => $path], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>404 - Página não encontrada</h1><p>' . htmlspecialchars($path) . '</p>';
}
