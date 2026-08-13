<?php
/**
 * Suíte de testes do projeto.
 *
 *   php backend/tests/run.php            roda tudo
 *   php backend/tests/run.php Variant    roda só as classes cujo nome casa
 *
 * Não exige Composer nem banco de dados: são testes de unidade sobre o domínio.
 * Para o que depende de banco ou de HTTP, os verificadores ficam em
 * backend/tools/ (concurrency_check, authz_check, identity_check, routes_check).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';
require __DIR__ . '/TestCase.php';

$filtro = $argv[1] ?? '';

$arquivos = glob(__DIR__ . '/Unit/*Test.php') ?: [];
sort($arquivos, SORT_STRING);

$total = 0;
$falhas = [];
$inicio = microtime(true);

foreach ($arquivos as $arquivo) {
    $classe = 'Tests\\Unit\\' . basename($arquivo, '.php');
    require_once $arquivo;
    if (!class_exists($classe)) {
        fwrite(STDERR, "Classe {$classe} não encontrada em {$arquivo}\n");
        exit(1);
    }
    if ($filtro !== '' && stripos($classe, $filtro) === false) {
        continue;
    }

    /** @var Tests\TestCase $teste */
    $teste = new $classe();
    $teste->executar();

    echo PHP_EOL . (new ReflectionClass($teste))->getShortName() . PHP_EOL;
    foreach ($teste->resultados as $r) {
        $total++;
        $nome = substr($r['nome'], strpos($r['nome'], '::') + 2);
        if ($r['ok']) {
            echo '  PASSA  ' . $nome . PHP_EOL;
        } else {
            echo '  FALHA  ' . $nome . PHP_EOL;
            echo '         ' . $r['erro'] . PHP_EOL;
            $falhas[] = $r['nome'] . ': ' . $r['erro'];
        }
    }
}

$ms = (int) round((microtime(true) - $inicio) * 1000);
echo PHP_EOL . $total . ' teste(s) em ' . $ms . 'ms — '
    . ($falhas === [] ? 'TUDO PASSOU' : count($falhas) . ' FALHARAM') . PHP_EOL;

exit($falhas === [] ? 0 : 1);
