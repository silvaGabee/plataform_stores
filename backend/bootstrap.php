<?php

if (!defined('PLATAFORM_ROOT')) {
    define('PLATAFORM_ROOT', dirname(__DIR__));
}
if (!defined('PLATAFORM_BACKEND')) {
    define('PLATAFORM_BACKEND', __DIR__);
}

$envFile = PLATAFORM_ROOT . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0b\"'");
            if ($name === '') {
                continue;
            }
            // O .env tem precedência sobre variáveis do sistema, de propósito.
            //
            // O padrão de mercado é o inverso (ambiente vence), mas aqui custou
            // caro: uma OPENROUTER_API_KEY antiga esquecida no ambiente do
            // Windows passou a sobrescrever a do .env, e a API respondia
            // "User not found" sem nada no projeto ter mudado. Como este
            // projeto é instalado copiando a pasta, o .env é a única fonte de
            // configuração que a pessoa realmente controla e enxerga.
            //
            // Para apontar a outro banco sem editar o arquivo, use uma variável
            // que NÃO esteja no .env (é assim que DB_NAME funciona).
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

/**
 * Autoload.
 *
 * Existe composer.json declarando PSR-4 e a versão mínima de PHP, mas a
 * aplicação NÃO depende do Composer para rodar: o deploy continua sendo copiar
 * a pasta. Se `vendor/` existir (máquina de desenvolvimento, CI), usa o
 * autoloader dele — que também carrega as ferramentas de análise; senão, o
 * registro manual abaixo, que faz o mesmo mapeamento App\ -> backend/app/.
 */
$vendorAutoload = PLATAFORM_ROOT . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(function (string $class) {
        $prefix = 'App\\';
        $baseDir = PLATAFORM_BACKEND . '/app/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) require $file;
    });
}
