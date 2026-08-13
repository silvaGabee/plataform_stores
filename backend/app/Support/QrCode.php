<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Gerador de QR Code (modo byte, correção de erro nível M).
 *
 * Existe para que o QR do PIX seja gerado aqui dentro. Antes o BR Code — com a
 * chave PIX do lojista e o valor da venda — era mandado no query string para
 * `api.qrserver.com`, um serviço gratuito de terceiros, a cada pagamento.
 *
 * Sai em SVG: nítido em qualquer tamanho, pequeno, e sem depender da extensão
 * GD (que não está instalada no ambiente de desenvolvimento).
 *
 * Cobre as versões 1 a 10, que dão até 213 bytes — o BR Code de um pedido tem
 * cerca de 130. Acima disso, lança.
 *
 * A implementação segue a ISO/IEC 18004. O teste em backend/tests/Unit
 * DECODIFICA o resultado com código independente e confere que volta o texto
 * original: é essa ida e volta que garante que o QR é legível, já que aqui não
 * há como escanear a imagem.
 */
final class QrCode
{
    /** Nível de correção M: recupera ~15% de dano. Bits do indicador: 00. */
    private const EC_LEVEL_BITS = 0b00;

    /**
     * Por versão: [codewords de correção por bloco, [ [qtd blocos, codewords de dados], ... ] ].
     * Valores da tabela 9 da ISO/IEC 18004 para o nível M.
     */
    private const BLOCOS_M = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    /** Centros dos padrões de alinhamento por versão. */
    private const ALINHAMENTO = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * Gera o QR como SVG.
     *
     * @param int $modulosDeBorda "zona silenciosa" — o padrão exige 4 módulos
     *                            em volta, senão o leitor não acha o código.
     */
    public static function toSvg(string $texto, int $modulosDeBorda = 4): string
    {
        $matriz = self::toMatrix($texto);
        $n = count($matriz);
        $lado = $n + 2 * $modulosDeBorda;

        // Um único <path> com todos os módulos escuros: bem menor que um <rect>
        // por módulo, e renderiza mais rápido.
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matriz[$y][$x]) {
                    $d .= 'M' . ($x + $modulosDeBorda) . ' ' . ($y + $modulosDeBorda) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lado . ' ' . $lado . '"'
            . ' shape-rendering="crispEdges" role="img" aria-label="QR Code para pagamento PIX">'
            . '<rect width="' . $lado . '" height="' . $lado . '" fill="#ffffff"/>'
            . '<path d="' . $d . '" fill="#000000"/>'
            . '</svg>';
    }

    /** SVG embutido como data URI, para usar direto em <img src="...">. */
    public static function toDataUri(string $texto, int $modulosDeBorda = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::toSvg($texto, $modulosDeBorda));
    }

    /**
     * Matriz final de módulos: true = escuro.
     *
     * @return list<list<bool>>
     */
    public static function toMatrix(string $texto): array
    {
        $versao = self::escolherVersao($texto);
        $codewords = self::montarCodewords($texto, $versao);

        // Gera as 8 máscaras e fica com a de menor penalidade, como manda o
        // padrão — é o que evita blocos uniformes que confundem o leitor.
        $melhor = null;
        $melhorPenalidade = PHP_INT_MAX;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $m = self::desenhar($versao, $codewords, $mascara);
            $p = self::penalidade($m);
            if ($p < $melhorPenalidade) {
                $melhorPenalidade = $p;
                $melhor = $m;
            }
        }

        return $melhor;
    }

    /** Menor versão que comporta o texto. */
    private static function escolherVersao(string $texto): int
    {
        $bytes = strlen($texto);
        foreach (array_keys(self::BLOCOS_M) as $versao) {
            if ($bytes <= self::capacidadeBytes($versao)) {
                return $versao;
            }
        }
        throw new InvalidArgumentException(
            'Texto longo demais para QR versão 10 (' . $bytes . ' bytes; máximo '
            . self::capacidadeBytes(10) . ').'
        );
    }

    private static function capacidadeBytes(int $versao): int
    {
        // Cabeçalho: 4 bits de modo + contador (8 bits até a v9, 16 da v10).
        $bitsCabecalho = 4 + ($versao >= 10 ? 16 : 8);

        return self::totalDataCodewords($versao) - (int) ceil($bitsCabecalho / 8);
    }

    private static function totalDataCodewords(int $versao): int
    {
        $total = 0;
        foreach (self::BLOCOS_M[$versao][1] as [$qtd, $dados]) {
            $total += $qtd * $dados;
        }

        return $total;
    }

    /** Codifica o texto e intercala os blocos de dados e correção. */
    private static function montarCodewords(string $texto, int $versao): array
    {
        $bits = '';
        $bits .= '0100';                                            // modo byte
        $bits .= str_pad(decbin(strlen($texto)), $versao >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($texto[$i])), 8, '0', STR_PAD_LEFT);
        }

        $totalDados = self::totalDataCodewords($versao);
        $capacidadeBits = $totalDados * 8;

        // Terminador: até 4 zeros, sem passar da capacidade.
        $bits .= str_repeat('0', min(4, $capacidadeBits - strlen($bits)));
        // Completa o último codeword.
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }
        // Preenchimento alternado definido pelo padrão.
        $preenchimento = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacidadeBits) {
            $bits .= $preenchimento[$i++ % 2];
        }

        $dados = [];
        for ($i = 0; $i < $capacidadeBits; $i += 8) {
            $dados[] = bindec(substr($bits, $i, 8));
        }

        // Divide em blocos e calcula a correção de cada um.
        [$ecPorBloco, $estrutura] = self::BLOCOS_M[$versao];
        $blocosDados = [];
        $blocosEc = [];
        $pos = 0;
        foreach ($estrutura as [$qtd, $tamanho]) {
            for ($b = 0; $b < $qtd; $b++) {
                $bloco = array_slice($dados, $pos, $tamanho);
                $pos += $tamanho;
                $blocosDados[] = $bloco;
                $blocosEc[] = self::reedSolomon($bloco, $ecPorBloco);
            }
        }

        // Intercalação: codeword 0 de cada bloco, depois codeword 1, etc.
        $saida = [];
        $maiorDados = max(array_map('count', $blocosDados));
        for ($i = 0; $i < $maiorDados; $i++) {
            foreach ($blocosDados as $bloco) {
                if (isset($bloco[$i])) {
                    $saida[] = $bloco[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPorBloco; $i++) {
            foreach ($blocosEc as $bloco) {
                $saida[] = $bloco[$i];
            }
        }

        return $saida;
    }

    // ------------------------------------------------------- Reed-Solomon

    /** @var array{0: list<int>, 1: list<int>}|null exp/log de GF(256) */
    private static ?array $gf = null;

    /** Tabelas de exponencial e logaritmo em GF(256), polinômio 0x11D. */
    private static function gf(): array
    {
        if (self::$gf === null) {
            $exp = array_fill(0, 512, 0);
            $log = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
            self::$gf = [$exp, $log];
        }

        return self::$gf;
    }

    /** Codewords de correção de um bloco. */
    private static function reedSolomon(array $dados, int $quantidade): array
    {
        [$exp, $log] = self::gf();

        // Polinômio gerador: produto de (x - a^i), i de 0 a quantidade-1.
        $gerador = [1];
        for ($i = 0; $i < $quantidade; $i++) {
            $novo = array_fill(0, count($gerador) + 1, 0);
            foreach ($gerador as $j => $coef) {
                $novo[$j] ^= $coef;
                $novo[$j + 1] ^= $coef === 0 ? 0 : $exp[($log[$coef] + $i) % 255];
            }
            $gerador = $novo;
        }

        $resto = array_merge($dados, array_fill(0, $quantidade, 0));
        for ($i = 0, $n = count($dados); $i < $n; $i++) {
            $coef = $resto[$i];
            if ($coef === 0) {
                continue;
            }
            $fator = $log[$coef];
            foreach ($gerador as $j => $g) {
                if ($g !== 0) {
                    $resto[$i + $j] ^= $exp[($log[$g] + $fator) % 255];
                }
            }
        }

        return array_slice($resto, count($dados), $quantidade);
    }

    // ----------------------------------------------------------- desenho

    /**
     * Monta a matriz completa para uma máscara.
     *
     * @return list<list<bool>>
     */
    private static function desenhar(int $versao, array $codewords, int $mascara): array
    {
        $n = 17 + 4 * $versao;
        $m = array_fill(0, $n, array_fill(0, $n, false));
        $reservado = array_fill(0, $n, array_fill(0, $n, false));

        // Padrões localizadores nos três cantos, com separador.
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $cx + $x;
                    $py = $cy + $y;
                    if ($px < 0 || $py < 0 || $px >= $n || $py >= $n) {
                        continue;
                    }
                    $borda = $x === -1 || $y === -1 || $x === 7 || $y === 7;
                    $anel = ($x === 0 || $x === 6 || $y === 0 || $y === 6);
                    $centro = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $m[$py][$px] = !$borda && ($anel || $centro);
                    $reservado[$py][$px] = true;
                }
            }
        }

        // Padrões de alinhamento, exceto onde colidiriam com os localizadores.
        $centros = self::ALINHAMENTO[$versao];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                if (($cx === 6 && $cy === 6)
                    || ($cx === 6 && $cy === $n - 7)
                    || ($cx === $n - 7 && $cy === 6)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $m[$cy + $y][$cx + $x] = max(abs($x), abs($y)) !== 1;
                        $reservado[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        // Linhas de temporização.
        for ($i = 8; $i < $n - 8; $i++) {
            $claro = $i % 2 === 0;
            $m[6][$i] = $claro;
            $m[$i][6] = $claro;
            $reservado[6][$i] = true;
            $reservado[$i][6] = true;
        }

        // Módulo escuro fixo e reserva das áreas de formato.
        $m[$n - 8][8] = true;
        $reservado[$n - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            if (!$reservado[8][$i]) {
                $reservado[8][$i] = true;
            }
            if (!$reservado[$i][8]) {
                $reservado[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reservado[8][$n - 1 - $i] = true;
            $reservado[$n - 1 - $i][8] = true;
        }

        // Informação de versão (obrigatória da versão 7 em diante).
        if ($versao >= 7) {
            $bitsVersao = self::bitsVersao($versao);
            for ($i = 0; $i < 18; $i++) {
                $bit = (bool) (($bitsVersao >> $i) & 1);
                $linha = intdiv($i, 3);
                $coluna = $i % 3;
                $m[$linha][$n - 11 + $coluna] = $bit;
                $reservado[$linha][$n - 11 + $coluna] = true;
                $m[$n - 11 + $coluna][$linha] = $bit;
                $reservado[$n - 11 + $coluna][$linha] = true;
            }
        }

        // Dados em ziguezague, de baixo para cima, duas colunas por vez.
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $indice = 0;
        $subindo = true;
        for ($colDir = $n - 1; $colDir > 0; $colDir -= 2) {
            if ($colDir === 6) {
                $colDir--;  // a coluna 6 é de temporização: pula
            }
            for ($passo = 0; $passo < $n; $passo++) {
                $y = $subindo ? $n - 1 - $passo : $passo;
                for ($d = 0; $d < 2; $d++) {
                    $x = $colDir - $d;
                    if ($reservado[$y][$x]) {
                        continue;
                    }
                    $bit = $indice < strlen($bits) && $bits[$indice] === '1';
                    $indice++;
                    $m[$y][$x] = $bit !== self::mascaraAplica($mascara, $x, $y);
                }
            }
            $subindo = !$subindo;
        }

        // Informação de formato (nível de correção + máscara), nos dois lugares.
        $formato = self::bitsFormato($mascara);
        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($formato >> $i) & 1);
            // Cópia junto ao localizador superior esquerdo.
            if ($i < 6) {
                $m[8][$i] = $bit;
            } elseif ($i === 6) {
                $m[8][7] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[7][8] = $bit;
            } else {
                $m[14 - $i][8] = $bit;
            }
            // Cópia dividida entre os outros dois cantos: os bits 0..6 descem
            // pela coluna 8 (7 módulos) e os bits 7..14 seguem pela linha 8.
            //
            // São 7 e não 8 na vertical: com 8, o último bit caía justamente
            // sobre o módulo escuro fixo em (n-8, 8), que não faz parte do
            // formato. O teste estrutural pegou; a leitura continuava
            // funcionando porque os leitores costumam usar a primeira cópia.
            if ($i < 7) {
                $m[$n - 1 - $i][8] = $bit;
            } else {
                $m[8][$n - 15 + $i] = $bit;
            }
        }

        return $m;
    }

    /** A máscara inverte o módulo quando a condição é verdadeira. */
    private static function mascaraAplica(int $mascara, int $x, int $y): bool
    {
        switch ($mascara) {
            case 0: return ($x + $y) % 2 === 0;
            case 1: return $y % 2 === 0;
            case 2: return $x % 3 === 0;
            case 3: return ($x + $y) % 3 === 0;
            case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
            case 5: return (($x * $y) % 2) + (($x * $y) % 3) === 0;
            case 6: return ((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0;
            case 7: return ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0;
            default: return false;
        }
    }

    /** 15 bits: nível de correção + máscara, com BCH e XOR do padrão. */
    private static function bitsFormato(int $mascara): int
    {
        $dados = (self::EC_LEVEL_BITS << 3) | $mascara;
        $resto = $dados << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($resto >> $i) & 1) {
                $resto ^= 0b10100110111 << ($i - 10);
            }
        }

        return (($dados << 10) | $resto) ^ 0b101010000010010;
    }

    /** 18 bits de informação de versão, com BCH. */
    private static function bitsVersao(int $versao): int
    {
        $resto = $versao << 12;
        for ($i = 17; $i >= 12; $i--) {
            if (($resto >> $i) & 1) {
                $resto ^= 0b1111100100101 << ($i - 12);
            }
        }

        return ($versao << 12) | $resto;
    }

    // -------------------------------------------------------- penalidade

    /** Pontuação das quatro regras do padrão: menor é melhor. */
    private static function penalidade(array $m): int
    {
        $n = count($m);
        $total = 0;

        // Regra 1: sequências de 5 ou mais módulos iguais, em linha e coluna.
        for ($i = 0; $i < $n; $i++) {
            for ($eixo = 0; $eixo < 2; $eixo++) {
                $seq = 1;
                for ($j = 1; $j < $n; $j++) {
                    $atual = $eixo === 0 ? $m[$i][$j] : $m[$j][$i];
                    $anterior = $eixo === 0 ? $m[$i][$j - 1] : $m[$j - 1][$i];
                    if ($atual === $anterior) {
                        $seq++;
                    } else {
                        if ($seq >= 5) {
                            $total += 3 + ($seq - 5);
                        }
                        $seq = 1;
                    }
                }
                if ($seq >= 5) {
                    $total += 3 + ($seq - 5);
                }
            }
        }

        // Regra 2: blocos 2x2 de cor uniforme.
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1]
                    && $m[$y][$x] === $m[$y + 1][$x]
                    && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }

        // Regra 3: padrão que imita o localizador (1:1:3:1:1 com zona clara).
        $alvo1 = [true, false, true, true, true, false, true, false, false, false, false];
        $alvo2 = array_reverse($alvo1);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                foreach ([0, 1] as $eixo) {
                    if (($eixo === 0 && $x + 11 > $n) || ($eixo === 1 && $y + 11 > $n)) {
                        continue;
                    }
                    $janela = [];
                    for ($k = 0; $k < 11; $k++) {
                        $janela[] = $eixo === 0 ? $m[$y][$x + $k] : $m[$y + $k][$x];
                    }
                    if ($janela === $alvo1 || $janela === $alvo2) {
                        $total += 40;
                    }
                }
            }
        }

        // Regra 4: desvio da proporção de 50% de módulos escuros.
        $escuros = 0;
        foreach ($m as $linha) {
            foreach ($linha as $v) {
                if ($v) {
                    $escuros++;
                }
            }
        }
        $percentual = ($escuros * 100) / ($n * $n);
        $total += 10 * (int) (abs($percentual - 50) / 5);

        return $total;
    }
}
