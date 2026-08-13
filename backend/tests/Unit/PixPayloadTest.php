<?php

namespace Tests\Unit;

use App\Services\PixService;
use Tests\TestCase;

/**
 * BR Code do PIX ("copia e cola").
 *
 * É o texto que o cliente cola no aplicativo do banco. Se estiver malformado, o
 * pagamento simplesmente não acontece — e o erro aparece no celular do cliente,
 * não no log do servidor. O formato é EMV: cada campo é
 * `tag (2 dígitos) + comprimento (2 dígitos) + valor`.
 *
 * O CRC16 no fim é o que o aplicativo confere primeiro; um byte errado ali e o
 * código é rejeitado sem explicação.
 */
final class PixPayloadTest extends TestCase
{
    private PixService $pix;

    protected function setUp(): void
    {
        // buildPixPayload não toca no banco; o repositório só é usado pelos
        // métodos que leem a configuração da loja.
        $this->pix = new PixService(
            new class extends \App\Repositories\StorePixConfigRepository {
                public function __construct()
                {
                }
            }
        );
    }

    private function payload(): string
    {
        return $this->pix->buildPixPayload(
            'exemplo@loja.test',
            'Loja Exemplo',
            'Sao Paulo',
            99.80,
            'Pedido42'
        );
    }

    public function testComecaComOIdentificadorDoFormato(): void
    {
        // "00" = Payload Format Indicator, comprimento "02", valor "01".
        $this->assertSame('000201', substr($this->payload(), 0, 6));
    }

    public function testContemOGuiDoPixEAChave(): void
    {
        $p = $this->payload();
        $this->assertTrue(str_contains($p, 'br.gov.bcb.pix'), 'sem o GUI do PIX');
        $this->assertTrue(str_contains($p, 'exemplo@loja.test'), 'sem a chave');
    }

    public function testValorApareceComDuasCasasEPonto(): void
    {
        // O padrão exige ponto decimal e nunca separador de milhar.
        $this->assertTrue(str_contains($this->payload(), '99.80'));

        $mil = $this->pix->buildPixPayload('k@x.test', 'L', 'SP', 1234.5, 'X');
        $this->assertTrue(str_contains($mil, '1234.50'), 'valor com milhar: ' . $mil);
        $this->assertFalse(str_contains($mil, '1,234'), 'não pode ter separador de milhar');
    }

    public function testTerminaComCrcDeQuatroHexadecimais(): void
    {
        $p = $this->payload();
        $this->assertSame('6304', substr($p, -8, 4), 'a tag do CRC deve preceder o valor');
        $this->assertTrue((bool) preg_match('/^[0-9A-F]{4}$/', substr($p, -4)), 'CRC: ' . substr($p, -4));
    }

    public function testCrcConfereComOCalculadoSobreORestante(): void
    {
        // É exatamente o que o aplicativo do banco faz ao ler o código.
        $p = $this->payload();
        $semCrc = substr($p, 0, -4);
        $informado = substr($p, -4);

        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($semCrc); $i < $n; $i++) {
            $crc ^= (ord($semCrc[$i]) & 0xFF) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        $this->assertSame(strtoupper(str_pad(dechex($crc & 0xFFFF), 4, '0', STR_PAD_LEFT)), $informado);
    }

    public function testComprimentosDeclaradosBatemComOsValores(): void
    {
        // Percorre o TLV do começo ao fim. Se algum comprimento estiver errado,
        // a varredura sai do lugar e não termina exatamente no fim da string —
        // que é como um código malformado se manifesta.
        $p = $this->payload();
        $i = 0;
        $tagsVistas = [];
        while ($i < strlen($p)) {
            $tag = substr($p, $i, 2);
            $len = (int) substr($p, $i + 2, 2);
            $this->assertTrue($len > 0, 'comprimento zero na tag ' . $tag);
            $tagsVistas[] = $tag;
            $i += 4 + $len;
        }
        $this->assertSame(strlen($p), $i, 'a varredura TLV não terminou no fim do payload');
        $this->assertTrue(in_array('63', $tagsVistas, true), 'sem a tag de CRC');
    }

    public function testNomeECidadeSaoTruncadosAoLimiteDoPadrao(): void
    {
        // O padrão limita nome a 25 e cidade a 15 caracteres; estourar faz o
        // aplicativo recusar.
        $p = $this->pix->buildPixPayload(
            'k@x.test',
            'Uma Loja Com Nome Absurdamente Longo Que Estoura',
            'Cidade Com Nome Muito Longo Tambem',
            10.0,
            'X'
        );
        $i = 0;
        while ($i < strlen($p)) {
            $tag = substr($p, $i, 2);
            $len = (int) substr($p, $i + 2, 2);
            if ($tag === '59') {
                $this->assertTrue($len <= 25, 'nome com ' . $len . ' caracteres');
            }
            if ($tag === '60') {
                $this->assertTrue($len <= 15, 'cidade com ' . $len . ' caracteres');
            }
            $i += 4 + $len;
        }
    }
}
