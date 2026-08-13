<?php

/**
 * Serve arquivos estáticos (assets e uploads).
 *
 * Incluído no TOPO do index.php, antes de session_start() e do bootstrap, por
 * dois motivos:
 *
 *  1. O session_start() faz o PHP mandar `Cache-Control: no-store, no-cache,
 *     must-revalidate` em toda resposta. Como os assets passavam por aqui
 *     DEPOIS da sessão, o navegador era instruído a nunca guardá-los — e
 *     rebaixava o CSS inteiro a cada page view. Não era falta de cache: era
 *     cache desligado de propósito, sem querer.
 *  2. Servir um arquivo não precisa de sessão, de .env, nem de autoloader.
 *
 * O ideal continua sendo apontar o DocumentRoot para esta pasta e deixar o
 * Apache servir os estáticos sozinho. Enquanto a instalação vive em
 * htdocs/plataform_stores/public/, o caminho pedido não existe em disco e a
 * requisição cai no index.php — este arquivo é o que a torna barata.
 *
 * @return bool true se serviu (e já encerrou a resposta)
 */

/** Tipos que sabemos servir. Extensão fora desta lista não é servida. */
function static_mime(string $ext): ?string
{
    static $mimes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    return $mimes[strtolower($ext)] ?? null;
}

/**
 * Envia o arquivo com cache e responde 304 quando o navegador já o tem.
 *
 * A URL dos assets carrega ?v=<mtime> (ver a função asset()), então o conteúdo
 * de uma URL nunca muda: pode ser guardado por um ano. Sem o parâmetro — caso
 * dos uploads — o cache é curto e revalidado pelo ETag.
 */
function static_serve(string $file, bool $versionado): void
{
    $mime = static_mime(pathinfo($file, PATHINFO_EXTENSION));
    if ($mime === null) {
        http_response_code(404);
        exit;
    }
    $mtime = (int) filemtime($file);
    $size = (int) filesize($file);
    $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

    header('Content-Type: ' . $mime);
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: ' . ($versionado
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=3600, must-revalidate'));

    // O navegador já tem esta versão: 304 e nenhum byte de corpo.
    $enviouEtag = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $enviouData = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    if ($enviouEtag === $etag || ($enviouData !== '' && @strtotime($enviouData) >= $mtime)) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . $size);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    readfile($file);
    exit;
}

/**
 * Resolve o caminho pedido dentro de $baseDir, sem deixar escapar.
 * Devolve o caminho real do arquivo, ou null.
 */
function static_resolve(string $baseDir, string $relativo): ?string
{
    if ($relativo === '' || str_contains($relativo, "\0")) {
        return null;
    }
    $base = realpath($baseDir);
    if ($base === false) {
        return null;
    }
    $real = realpath($baseDir . '/' . $relativo);
    // O realpath resolve ".." — comparar com o prefixo é o que impede servir
    // qualquer arquivo do disco.
    if ($real === false || !is_file($real) || strncmp($real, $base, strlen($base)) !== 0) {
        return null;
    }

    return $real;
}

/** Tenta servir o caminho pedido. Encerra a resposta se conseguir. */
function static_try(string $path): bool
{
    $mapa = [
        '#^/assets/(.+)$#' => __DIR__ . '/assets',
        '#^/uploads/(.+)$#' => __DIR__ . '/uploads',
    ];
    foreach ($mapa as $regex => $baseDir) {
        if (!preg_match($regex, $path, $m)) {
            continue;
        }
        $file = static_resolve($baseDir, rawurldecode($m[1]));
        if ($file === null) {
            return false;
        }
        static_serve($file, isset($_GET['v']) && $_GET['v'] !== '');
    }

    return false;
}
