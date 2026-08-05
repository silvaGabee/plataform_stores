<?php
/**
 * Confere que toda rota declara um requisito de acesso válido.
 *
 *   php backend/tools/routes_check.php
 *
 * O Guard já recusa em tempo de execução uma rota sem requisito — mas só quando
 * alguém a chama. Este script encontra o problema antes, e serve de porta para
 * o CI quando a Fase 4 chegar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Auth\Permissions;
use App\Http\Guard;

$especiais = [Guard::PUBLICO, Guard::AUTENTICADO];
$problemas = [];
$contagem = [];
$total = 0;

foreach (['api', 'web'] as $arquivo) {
    $rotas = require PLATAFORM_BACKEND . "/routes/{$arquivo}.php";
    foreach ($rotas as $padrao => $handler) {
        $total++;
        $requisito = $handler[2] ?? null;

        if (!is_string($requisito) || $requisito === '') {
            $problemas[] = "sem requisito declarado:  {$padrao}";
            continue;
        }
        if (!in_array($requisito, $especiais, true) && !Permissions::existe($requisito)) {
            $problemas[] = "permissão inexistente \"{$requisito}\":  {$padrao}";
            continue;
        }
        // Permissão de loja precisa de {slug} para o Guard resolver a loja.
        if (strpos($requisito, 'store.') === 0 && strpos($padrao, '{slug}') === false) {
            $problemas[] = "permissão de loja sem {slug} na rota:  {$padrao}";
            continue;
        }
        // O método e a classe existem mesmo?
        [$classe, $metodo] = $handler;
        if (!class_exists($classe) || !method_exists($classe, $metodo)) {
            $problemas[] = "handler inexistente {$classe}::{$metodo}:  {$padrao}";
            continue;
        }
        $contagem[$requisito] = ($contagem[$requisito] ?? 0) + 1;
    }
}

ksort($contagem);
echo PHP_EOL . 'Rotas por requisito' . PHP_EOL;
foreach ($contagem as $requisito => $n) {
    printf("  %-24s %d%s" . PHP_EOL, $requisito, $n, $requisito === Guard::PUBLICO ? '   <- aberto a qualquer visitante' : '');
}

// Permissões declaradas na matriz que nenhuma rota usa: ou sobram, ou alguém
// esqueceu de aplicá-las.
$semUso = array_diff(Permissions::todas(), array_keys($contagem));
if ($semUso !== []) {
    echo PHP_EOL . 'Permissões da matriz sem rota (verificadas dentro de controllers):' . PHP_EOL;
    foreach ($semUso as $p) {
        echo '  ' . $p . PHP_EOL;
    }
}

echo PHP_EOL . $total . ' rotas verificadas.' . PHP_EOL;
if ($problemas !== []) {
    echo PHP_EOL . 'PROBLEMAS:' . PHP_EOL;
    foreach ($problemas as $p) {
        echo '  ' . $p . PHP_EOL;
    }
    exit(1);
}
echo 'Nenhum problema.' . PHP_EOL;
exit(0);
