<?php
/**
 * Verificação de sintaxe de todo o PHP e JS do projeto.
 *
 *   php backend/tests/lint.php
 *
 * Faz o que o `php -l` faz, mas sobre a árvore inteira e com saída resumida —
 * é a primeira porta do CI, antes de qualquer teste.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);
$php = PHP_BINARY;
$erros = [];
$contagem = ['php' => 0, 'js' => 0];

/** @return list<string> */
function arquivos(string $dir, string $extensao): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $arquivo) {
        $caminho = str_replace('\\', '/', $arquivo->getPathname());
        // vendor/ é código de terceiros; assets/js/vendor idem.
        if (str_contains($caminho, '/vendor/') || str_contains($caminho, '/node_modules/')) {
            continue;
        }
        if (strtolower($arquivo->getExtension()) === $extensao) {
            $out[] = $caminho;
        }
    }
    sort($out, SORT_STRING);

    return $out;
}

echo 'PHP' . PHP_EOL;
foreach (arquivos($raiz . '/backend', 'php') as $arquivo) {
    $contagem['php']++;
    $saida = [];
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($arquivo) . ' 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        $erros[] = $arquivo . ': ' . implode(' ', $saida);
    }
}
foreach (arquivos($raiz . '/frontend/public', 'php') as $arquivo) {
    $contagem['php']++;
    $saida = [];
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($arquivo) . ' 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        $erros[] = $arquivo . ': ' . implode(' ', $saida);
    }
}
echo '  ' . $contagem['php'] . ' arquivo(s) verificado(s)' . PHP_EOL;

// O node é opcional: nem toda máquina que roda o projeto o tem instalado.
exec('node --version 2>&1', $saidaNode, $temNode);
echo PHP_EOL . 'JavaScript' . PHP_EOL;
if ($temNode !== 0) {
    echo '  node não encontrado — verificação de JS ignorada' . PHP_EOL;
} else {
    foreach (arquivos($raiz . '/frontend/public/assets/js', 'js') as $arquivo) {
        $contagem['js']++;
        $saida = [];
        exec('node --check ' . escapeshellarg($arquivo) . ' 2>&1', $saida, $codigo);
        if ($codigo !== 0) {
            $erros[] = $arquivo . ': ' . implode(' ', $saida);
        }
    }
    echo '  ' . $contagem['js'] . ' arquivo(s) verificado(s)' . PHP_EOL;
}

echo PHP_EOL;
if ($erros !== []) {
    echo 'ERROS DE SINTAXE:' . PHP_EOL;
    foreach ($erros as $erro) {
        echo '  ' . $erro . PHP_EOL;
    }
    exit(1);
}
echo 'Sintaxe ok.' . PHP_EOL;
exit(0);
