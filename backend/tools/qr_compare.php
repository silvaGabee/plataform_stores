<?php
/**
 * Confere o gerador de QR contra uma referência externa.
 *
 *   php backend/tools/qr_compare.php [texto]
 *
 * O teste de unidade faz ida e volta com um decodificador próprio, e isso tem
 * um limite conhecido: se codificador e decodificador errarem da MESMA forma,
 * a ida e volta passa e o leitor de verdade falha. Aqui a verificação é
 * cruzada, contra um QR gerado por outra implementação:
 *
 *   1. decodifica a REFERÊNCIA com o nosso decodificador
 *      -> se sair o texto certo, o nosso decodificador segue o padrão;
 *   2. compara os padrões fixos (localizadores, temporização, alinhamento)
 *      -> esses não dependem da máscara escolhida e têm de bater exatamente.
 *
 * Precisa de internet. Não roda no CI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Support\QrCode;

$texto = $argv[1] ?? 'HELLO';

// ---------------------------------------------------------------- referência
$url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
    'data' => $texto,
    'size' => '210x210',
    'format' => 'svg',
    'qzone' => 0,
    'ecc' => 'M',
]);
$svg = @file_get_contents($url);
if ($svg === false) {
    fwrite(STDERR, "Não foi possível buscar a referência (precisa de internet).\n");
    exit(1);
}

// Cada módulo escuro é um subpath "M x,y l L,0 0,L -L,0 z": o L é o tamanho do
// módulo em pixels, explícito. Deduzi-lo pela menor distância entre coordenadas
// dava errado quando o tamanho pedido não era múltiplo exato do lado do QR — e
// aí o "lado" calculado saía par, o que não existe em QR.
preg_match_all('/M\s*([\d.]+)\s*,\s*([\d.]+)\s*l\s*([\d.]+)\s*,\s*0/', $svg, $mm, PREG_SET_ORDER);
if (!$mm) {
    fwrite(STDERR, "Formato da referência não reconhecido.\n");
    exit(1);
}
$passo = (float) $mm[0][3];
$maxX = 0;
foreach ($mm as $p) {
    $maxX = max($maxX, (int) round(((float) $p[1]) / $passo));
}
// O canto inferior direito sempre tem módulo escuro (padrão localizador), então
// o maior índice + 1 é o lado da matriz.
$lado = $maxX + 1;
if (($lado - 17) % 4 !== 0 || $lado < 21) {
    fwrite(STDERR, "Lado inválido para QR: {$lado}\n");
    exit(1);
}
$ref = array_fill(0, $lado, array_fill(0, $lado, false));
foreach ($mm as $p) {
    $x = (int) round(((float) $p[1]) / $passo);
    $y = (int) round(((float) $p[2]) / $passo);
    if ($x >= 0 && $y >= 0 && $x < $lado && $y < $lado) {
        $ref[$y][$x] = true;
    }
}

$meu = QrCode::toMatrix($texto);

echo PHP_EOL . 'Texto: ' . json_encode($texto) . PHP_EOL;
printf("  referência: %dx%d      nosso: %dx%d%s", $lado, $lado, count($meu), count($meu), PHP_EOL);
if ($lado !== count($meu)) {
    fwrite(STDERR, "Versões diferentes — comparação não se aplica.\n");
    exit(1);
}

// ------------------------- 1) o nosso decodificador lê o QR de outra pessoa?
$lido = decodificar($ref);
$ok1 = $lido === $texto;
echo PHP_EOL . '1) Nosso decodificador lendo a REFERÊNCIA' . PHP_EOL;
echo '   leu: ' . json_encode($lido) . PHP_EOL;
echo '   ' . ($ok1 ? 'OK — nosso decodificador segue o padrão' : 'FALHOU — o decodificador do teste tem erro, e a ida e volta não prova nada') . PHP_EOL;

// ------------------------------ 2) padrões fixos, independentes da máscara
$difFixos = 0;
$total = 0;
$reservado = mapaFixos($lado);
for ($y = 0; $y < $lado; $y++) {
    for ($x = 0; $x < $lado; $x++) {
        if (!$reservado[$y][$x]) {
            continue;
        }
        $total++;
        if ($ref[$y][$x] !== $meu[$y][$x]) {
            $difFixos++;
        }
    }
}
$ok2 = $difFixos === 0;
echo PHP_EOL . '2) Padrões fixos (localizadores, temporização, alinhamento)' . PHP_EOL;
printf("   %d de %d módulos diferentes%s", $difFixos, $total, PHP_EOL);
echo '   ' . ($ok2 ? 'OK — posicionamento idêntico ao da referência' : 'FALHOU — os padrões fixos estão em lugar errado') . PHP_EOL;

// ------------------------------------------- 3) e o nosso, lido de volta?
$ok3 = decodificar($meu) === $texto;
echo PHP_EOL . '3) Nosso decodificador lendo o NOSSO QR' . PHP_EOL;
echo '   ' . ($ok3 ? 'OK' : 'FALHOU') . PHP_EOL;

echo PHP_EOL . (($ok1 && $ok2 && $ok3) ? 'TUDO PASSOU' : 'HÁ DIVERGÊNCIA') . PHP_EOL;
exit(($ok1 && $ok2 && $ok3) ? 0 : 1);

// =====================================================================

/** Marca os módulos que NÃO são dados (independem da máscara e do conteúdo). */
function mapaFixos(int $n): array
{
    $versao = (int) (($n - 17) / 4);
    $r = array_fill(0, $n, array_fill(0, $n, false));
    foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $r[$cy + $y][$cx + $x] = true;
            }
        }
    }
    for ($i = 8; $i < $n - 8; $i++) {
        $r[6][$i] = true;
        $r[$i][6] = true;
    }
    $centros = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ][$versao] ?? [];
    foreach ($centros as $cy) {
        foreach ($centros as $cx) {
            if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $n - 7) || ($cx === $n - 7 && $cy === 6)) {
                continue;
            }
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $r[$cy + $y][$cx + $x] = true;
                }
            }
        }
    }
    // O módulo escuro fixo.
    $r[$n - 8][8] = true;

    return $r;
}

/** Decodificador — o mesmo do teste de unidade. */
function decodificar(array $m): string
{
    $n = count($m);
    $versao = (int) (($n - 17) / 4);
    $mascara = lerMascara($m);
    $reservado = mapaReservado($n, $versao);

    $bits = '';
    $subindo = true;
    for ($colDir = $n - 1; $colDir > 0; $colDir -= 2) {
        if ($colDir === 6) {
            $colDir--;
        }
        for ($passo = 0; $passo < $n; $passo++) {
            $y = $subindo ? $n - 1 - $passo : $passo;
            for ($d = 0; $d < 2; $d++) {
                $x = $colDir - $d;
                if ($reservado[$y][$x]) {
                    continue;
                }
                $bits .= ($m[$y][$x] !== mascara($mascara, $x, $y)) ? '1' : '0';
            }
        }
        $subindo = !$subindo;
    }

    $codewords = [];
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $codewords[] = bindec(substr($bits, $i, 8));
    }
    $dados = desintercalar($codewords, $versao);

    $fluxo = '';
    foreach ($dados as $cw) {
        $fluxo .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
    }
    $modo = substr($fluxo, 0, 4);
    if ($modo !== '0100') {
        return '(modo ' . $modo . ', esperado 0100/byte)';
    }
    $bitsContador = $versao >= 10 ? 16 : 8;
    $tamanho = bindec(substr($fluxo, 4, $bitsContador));
    $texto = '';
    $pos = 4 + $bitsContador;
    for ($i = 0; $i < $tamanho; $i++) {
        $texto .= chr(bindec(substr($fluxo, $pos, 8)));
        $pos += 8;
    }

    return $texto;
}

function lerMascara(array $m): int
{
    $bits = 0;
    for ($i = 0; $i < 15; $i++) {
        if ($i < 6) {
            $bit = $m[8][$i];
        } elseif ($i === 6) {
            $bit = $m[8][7];
        } elseif ($i === 7) {
            $bit = $m[8][8];
        } elseif ($i === 8) {
            $bit = $m[7][8];
        } else {
            $bit = $m[14 - $i][8];
        }
        $bits |= ((int) $bit) << $i;
    }

    return (($bits ^ 0b101010000010010) >> 10) & 0b111;
}

function mascara(int $mascara, int $x, int $y): bool
{
    switch ($mascara) {
        case 0: return ($x + $y) % 2 === 0;
        case 1: return $y % 2 === 0;
        case 2: return $x % 3 === 0;
        case 3: return ($x + $y) % 3 === 0;
        case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
        case 5: return (($x * $y) % 2) + (($x * $y) % 3) === 0;
        case 6: return ((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0;
        default: return ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0;
    }
}

function mapaReservado(int $n, int $versao): array
{
    $r = array_fill(0, $n, array_fill(0, $n, false));
    foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $px = $cx + $x;
                $py = $cy + $y;
                if ($px >= 0 && $py >= 0 && $px < $n && $py < $n) {
                    $r[$py][$px] = true;
                }
            }
        }
    }
    $centros = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ][$versao] ?? [];
    foreach ($centros as $cy) {
        foreach ($centros as $cx) {
            if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $n - 7) || ($cx === $n - 7 && $cy === 6)) {
                continue;
            }
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $r[$cy + $y][$cx + $x] = true;
                }
            }
        }
    }
    for ($i = 8; $i < $n - 8; $i++) {
        $r[6][$i] = true;
        $r[$i][6] = true;
    }
    $r[$n - 8][8] = true;
    for ($i = 0; $i < 9; $i++) {
        $r[8][$i] = true;
        $r[$i][8] = true;
    }
    for ($i = 0; $i < 8; $i++) {
        $r[8][$n - 1 - $i] = true;
        $r[$n - 1 - $i][8] = true;
    }
    if ($versao >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $linha = intdiv($i, 3);
            $coluna = $i % 3;
            $r[$linha][$n - 11 + $coluna] = true;
            $r[$n - 11 + $coluna][$linha] = true;
        }
    }

    return $r;
}

function desintercalar(array $codewords, int $versao): array
{
    [, $estrutura] = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ][$versao];
    $tamanhos = [];
    foreach ($estrutura as [$qtd, $tam]) {
        for ($i = 0; $i < $qtd; $i++) {
            $tamanhos[] = $tam;
        }
    }
    $blocos = array_fill(0, count($tamanhos), []);
    $indice = 0;
    foreach (range(0, max($tamanhos) - 1) as $i) {
        foreach ($tamanhos as $b => $tam) {
            if ($i < $tam && isset($codewords[$indice])) {
                $blocos[$b][] = $codewords[$indice++];
            }
        }
    }

    return array_merge(...$blocos);
}
