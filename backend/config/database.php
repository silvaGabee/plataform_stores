<?php

/**
 * Configuração do banco de dados.
 *
 * Os valores padrão são os do XAMPP (root, senha em branco). Cada um pode ser
 * sobrescrito pelo .env — é o que permite apontar para outro banco sem editar
 * arquivo versionado, seja em produção, seja para testar uma migration numa
 * cópia antes de rodá-la no banco real.
 *
 * Lê direto de getenv() porque este arquivo é carregado por scripts de CLI que
 * não necessariamente já incluíram Helpers/functions.php.
 */
$env = static function (string $chave, string $padrao): string {
    $valor = getenv($chave);
    if ($valor === false) {
        $valor = $_ENV[$chave] ?? '';
    }
    $valor = trim((string) $valor);

    return $valor !== '' ? $valor : $padrao;
};

return [
    'host'     => $env('DB_HOST', 'localhost'),
    'dbname'   => $env('DB_NAME', 'plataform_stores'),
    'charset'  => $env('DB_CHARSET', 'utf8mb4'),
    'username' => $env('DB_USER', 'root'),
    // Senha em branco é válida no XAMPP, então o padrão precisa ser '' mesmo.
    'password' => (string) (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($_ENV['DB_PASSWORD'] ?? '')),
];
