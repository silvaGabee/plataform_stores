<?php

namespace Tests\Unit;

use App\Support\QrCode;
use Tests\TestCase;

/**
 * QR Code.
 *
 * Aqui não há como escanear a imagem com um celular, e um QR sutilmente errado
 * é um pagamento que falha no caixa — longe de qualquer log. Por isso o teste
 * central DECODIFICA a matriz gerada, com código escrito a partir do padrão e
 * independente do codificador, e confere que volta exatamente o texto original.
 *
 * A ida e volta cobre o que mais costuma sair errado: ordem dos bits,
 * aplicação da máscara, posicionamento dos módulos e intercalação dos blocos.
 */
final class QrCodeTest extends TestCase
{
    private const PIX_EXEMPLO = '00020126390014br.gov.bcb.pix0117exemplo@loja.test5204000053039865406179.805802BR5912Loja Exemplo6009Sao Paulo62120508Pedido426304ABCD';

    // ------------------------------------------------------ ida e volta

    public function testTextoCurtoVoltaIgual(): void
    {
        $this->assertSame('OI', $this->decodificar(QrCode::toMatrix('OI')));
    }

    public function testPayloadPixVoltaIgual(): void
    {
        // O caso que importa: 133 bytes, o tamanho real de um BR Code.
        $this->assertSame(self::PIX_EXEMPLO, $this->decodificar(QrCode::toMatrix(self::PIX_EXEMPLO)));
    }

    public function testVariosTamanhosVoltamIguais(): void
    {
        // Cada faixa cai numa versão diferente do QR, com estrutura de blocos
        // própria — é onde a intercalação erra, se errar.
        foreach ([1, 9, 14, 27, 53, 84, 106, 122, 152, 180, 213] as $tamanho) {
            $texto = substr(str_repeat('PIX-0123456789/ABCdef.', 40), 0, $tamanho);
            $this->assertSame($texto, $this->decodificar(QrCode::toMatrix($texto)), 'tamanho ' . $tamanho);
        }
    }

    public function testAcentosEBytesAltos(): void
    {
        $texto = 'Ação — São Paulo, R$ 179,80';
        $this->assertSame($texto, $this->decodificar(QrCode::toMatrix($texto)));
    }

    public function testAcimaDoLimiteLanca(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
            fn () => QrCode::toMatrix(str_repeat('x', 300))
        );
    }

    // ------------------------------------------------------- estrutura

    public function testTamanhoDaMatrizSegueAVersao(): void
    {
        // lado = 17 + 4 * versão. 133 bytes exigem a versão 8 => 17 + 32 = 49.
        $this->assertSame(49, count(QrCode::toMatrix(self::PIX_EXEMPLO)));
        $this->assertSame(21, count(QrCode::toMatrix('OI')));  // versão 1
    }

    public function testPadroesLocalizadoresNosTresCantos(): void
    {
        // Sem os três olhos, o leitor não encontra nem orienta o código.
        $m = QrCode::toMatrix(self::PIX_EXEMPLO);
        $n = count($m);
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
            $this->assertTrue($m[$cy][$cx], 'canto do localizador');
            $this->assertTrue($m[$cy + 3][$cx + 3], 'centro do localizador');
            $this->assertFalse($m[$cy + 1][$cx + 1], 'anel claro do localizador');
        }
    }

    public function testLinhasDeTemporizacaoAlternam(): void
    {
        $m = QrCode::toMatrix(self::PIX_EXEMPLO);
        for ($i = 8; $i < count($m) - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], 'temporização horizontal em ' . $i);
            $this->assertSame($i % 2 === 0, $m[$i][6], 'temporização vertical em ' . $i);
        }
    }

    public function testModuloEscuroObrigatorio(): void
    {
        $m = QrCode::toMatrix(self::PIX_EXEMPLO);
        $this->assertTrue($m[count($m) - 8][8]);
    }

    // ------------------------------------------------------------- SVG

    public function testSvgTemZonaSilenciosaEModulos(): void
    {
        $svg = QrCode::toSvg('OI');
        // 21 módulos + 4 de borda de cada lado = 29.
        $this->assertTrue(str_contains($svg, 'viewBox="0 0 29 29"'), $svg);
        $this->assertTrue(str_contains($svg, '<path d="M'), 'sem módulos desenhados');
        $this->assertTrue(str_contains($svg, 'fill="#ffffff"'), 'sem fundo claro');
    }

    public function testDataUriEhSvgValido(): void
    {
        $uri = QrCode::toDataUri('OI');
        $this->assertTrue(str_starts_with($uri, 'data:image/svg+xml;base64,'));
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
        $this->assertTrue(str_starts_with($svg, '<svg '));
    }

    // =================================================================
    // Decodificador independente, escrito a partir da ISO/IEC 18004.
    // Não usa nada de QrCode além da matriz.
    // =================================================================

    /** @param list<list<bool>> $m */
    private function decodificar(array $m): string
    {
        $n = count($m);
        $versao = (int) (($n - 17) / 4);
        $mascara = $this->lerMascara($m);
        $reservado = $this->mapaReservado($n, $versao);

        // Lê os módulos em ziguezague, desfazendo a máscara.
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
                    $bits .= ($m[$y][$x] !== $this->mascara($mascara, $x, $y)) ? '1' : '0';
                }
            }
            $subindo = !$subindo;
        }

        $codewords = [];
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        $dados = $this->desintercalar($codewords, $versao);

        // Cabeçalho: 4 bits de modo (0100 = byte) + contador.
        $fluxo = '';
        foreach ($dados as $cw) {
            $fluxo .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $modo = substr($fluxo, 0, 4);
        if ($modo !== '0100') {
            return '(modo inesperado: ' . $modo . ')';
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

    /** Lê a máscara da informação de formato e desfaz o XOR do padrão. */
    private function lerMascara(array $m): int
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
        $bits ^= 0b101010000010010;

        // Os 5 bits de dados ficam no topo: 2 de nível + 3 de máscara.
        return ($bits >> 10) & 0b111;
    }

    private function mascara(int $mascara, int $x, int $y): bool
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

    /** Marca tudo que não é dado: localizadores, alinhamento, formato, versão. */
    private function mapaReservado(int $n, int $versao): array
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
        ][$versao];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                if (($cx === 6 && $cy === 6)
                    || ($cx === 6 && $cy === $n - 7)
                    || ($cx === $n - 7 && $cy === 6)) {
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

    /** Desfaz a intercalação e devolve só os codewords de dados, em ordem. */
    private function desintercalar(array $codewords, int $versao): array
    {
        $tabela = [
            1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]],
            4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]],
            7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]],
            9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
        ][$versao];
        [, $estrutura] = $tabela;

        $tamanhos = [];
        foreach ($estrutura as [$qtd, $tam]) {
            for ($i = 0; $i < $qtd; $i++) {
                $tamanhos[] = $tam;
            }
        }
        $blocos = array_fill(0, count($tamanhos), []);
        $indice = 0;
        $maior = max($tamanhos);
        for ($i = 0; $i < $maior; $i++) {
            foreach ($tamanhos as $b => $tam) {
                if ($i < $tam) {
                    $blocos[$b][] = $codewords[$indice++];
                }
            }
        }

        return array_merge(...$blocos);
    }
}
