<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Estoque por variação.
 *
 * É o cálculo que decide se uma venda pode acontecer. Um erro aqui vende o que
 * não existe (ou recusa o que existe), então cada caso abaixo corresponde a uma
 * forma de o produto ser modelado no banco.
 *
 * Há dois formatos de variação convivendo: a MATRIZ cor × tamanho, gravada como
 * linhas `combinacao` com valor "Cor|Tamanho"; e a variação simples, uma linha
 * por valor. Mais as linhas `_meta`, que guardam configuração (eixo, cor em hex)
 * e nunca contam como estoque.
 */
final class VariantStockTest extends TestCase
{
    /** Produto com matriz cor × numeração. */
    private function produtoComMatriz(): array
    {
        return [
            'id' => 1,
            'name' => 'Tênis',
            'stock_quantity' => 12,
            'variants' => [
                ['variant_type' => '_meta', 'variant_value' => 'axis:numeracao', 'stock_quantity' => 0],
                ['variant_type' => '_meta', 'variant_value' => 'color_hex:Preto|#171717', 'stock_quantity' => 0],
                ['variant_type' => 'combinacao', 'variant_value' => 'Preto|40', 'stock_quantity' => 6],
                ['variant_type' => 'combinacao', 'variant_value' => 'Preto|41', 'stock_quantity' => 6],
                ['variant_type' => 'combinacao', 'variant_value' => 'Verde|40', 'stock_quantity' => 0],
            ],
        ];
    }

    /** Produto com variação simples (uma dimensão). */
    private function produtoComVariacaoSimples(): array
    {
        return [
            'id' => 2,
            'name' => 'Camisa',
            'stock_quantity' => 7,
            'variants' => [
                ['variant_type' => 'tamanho', 'variant_value' => 'M', 'stock_quantity' => 4],
                ['variant_type' => 'tamanho', 'variant_value' => 'G', 'stock_quantity' => 3],
            ],
        ];
    }

    private function produtoSemVariacao(): array
    {
        return ['id' => 3, 'name' => 'Corda', 'stock_quantity' => 12, 'variants' => []];
    }

    // ------------------------------------------------------ tem variação?

    public function testDetectaProdutoComESemVariacao(): void
    {
        $this->assertTrue(product_has_variants($this->produtoComMatriz()));
        $this->assertTrue(product_has_variants($this->produtoComVariacaoSimples()));
        $this->assertFalse(product_has_variants($this->produtoSemVariacao()));
    }

    public function testProdutoMeioConfiguradoContaComoTendoVariacao(): void
    {
        // Produto com o eixo definido mas nenhuma combinação cadastrada: o
        // gerente começou a configurar e parou.
        $produto = ['id' => 4, 'stock_quantity' => 5, 'variants' => [
            ['variant_type' => '_meta', 'variant_value' => 'axis:tamanho', 'stock_quantity' => 0],
        ]];

        // Conta como "tem variação" DE PROPÓSITO, e é o comportamento seguro:
        // a venda é recusada por falta de combinação. Se respondesse false, o
        // produto seria vendido contra stock_quantity, sem baixa em variação
        // nenhuma — estoque descontrolado em vez de um erro visível.
        $this->assertTrue(product_has_variants($produto));
        $this->assertSame(0, product_sale_available_stock($produto, null));
        $this->assertSame(0, product_sale_available_stock($produto, 'M'));
    }

    // ------------------------------------------------------- estoque total

    public function testTotalIgnoraLinhasDeConfiguracao(): void
    {
        // 6 + 6 + 0 = 12; as duas linhas _meta não entram.
        $this->assertSame(12, product_variants_total_stock($this->produtoComMatriz()['variants']));
    }

    public function testTotalNaoAceitaNegativo(): void
    {
        $variants = [['variant_type' => 'tamanho', 'variant_value' => 'M', 'stock_quantity' => -5]];
        $this->assertSame(0, product_variants_total_stock($variants));
    }

    // ------------------------------------------------- estoque para venda

    public function testSemVariacaoUsaOEstoqueDoProduto(): void
    {
        $this->assertSame(12, product_sale_available_stock($this->produtoSemVariacao(), null));
    }

    public function testComMatrizExigeCombinacao(): void
    {
        $p = $this->produtoComMatriz();
        $this->assertSame(6, product_sale_available_stock($p, 'Preto|40'));
        $this->assertSame(0, product_sale_available_stock($p, 'Verde|40'));
    }

    public function testProdutoComVariacaoSemChaveNaoTemEstoqueVendavel(): void
    {
        // Este é o guarda que impede vender "um tênis" sem dizer qual número:
        // o pedido precisa da combinação, senão não há o que baixar.
        $this->assertSame(0, product_sale_available_stock($this->produtoComMatriz(), null));
        $this->assertSame(0, product_sale_available_stock($this->produtoComMatriz(), ''));
    }

    public function testCombinacaoInexistenteNaoTemEstoque(): void
    {
        $this->assertSame(0, product_sale_available_stock($this->produtoComMatriz(), 'Rosa|38'));
    }

    public function testVariacaoSimplesPeloValor(): void
    {
        $p = $this->produtoComVariacaoSimples();
        $this->assertSame(4, product_sale_available_stock($p, 'M'));
        $this->assertSame(3, product_sale_available_stock($p, 'G'));
        $this->assertSame(0, product_sale_available_stock($p, 'GG'));
    }

    public function testVariacaoSimplesPelaChaveTipoDoisPontosValor(): void
    {
        // O carrinho pode gravar "tamanho:M" em vez de só "M".
        $this->assertSame(4, product_sale_available_stock($this->produtoComVariacaoSimples(), 'tamanho:M'));
    }

    // ------------------------------------------------------------- rótulos

    public function testRotuloDaCombinacaoUsaOEixoDoProduto(): void
    {
        $p = $this->produtoComMatriz();
        $p['variants_matrix'] = product_variants_rows_to_matrix($p['variants']);
        // O eixo é "numeracao", então o rótulo fala em Numeração — não "Tamanho".
        $rotulo = product_variant_key_label($p, 'Preto|40');
        $this->assertTrue(str_contains($rotulo, 'Preto'), 'rótulo: ' . $rotulo);
        $this->assertTrue(str_contains($rotulo, '40'), 'rótulo: ' . $rotulo);
    }

    public function testRotuloVazioQuandoNaoHaVariacao(): void
    {
        $this->assertSame('', product_variant_key_label($this->produtoSemVariacao(), null));
    }

    // ------------------------------------------------- matriz <-> linhas

    public function testMatrizReconstruidaDasLinhas(): void
    {
        $matriz = product_variants_rows_to_matrix($this->produtoComMatriz()['variants']);

        $this->assertSame('numeracao', $matriz['axis']);
        $this->assertSame(6, $matriz['stock']['40']['Preto']);
        $this->assertSame(0, $matriz['stock']['40']['Verde']);
    }

    public function testLinhasSemEixoNaoFormamMatriz(): void
    {
        // Sem a linha _meta axis:, não dá para saber se "40" é tamanho ou
        // numeração — e a matriz não pode ser montada às cegas.
        $semEixo = array_values(array_filter(
            $this->produtoComMatriz()['variants'],
            static fn (array $r): bool => !str_starts_with((string) $r['variant_value'], 'axis:')
        ));
        $this->assertNull(product_variants_rows_to_matrix($semEixo));
    }

    public function testIdaEVoltaPreservaOEstoque(): void
    {
        // matriz -> linhas -> matriz tem de dar na mesma, senão editar um
        // produto no painel corrompe o estoque em silêncio.
        $original = product_variants_rows_to_matrix($this->produtoComMatriz()['variants']);
        $linhas = product_variants_matrix_to_rows($original);
        $volta = product_variants_rows_to_matrix($linhas);

        $this->assertSame($original['axis'], $volta['axis']);
        $this->assertSame($original['stock']['40']['Preto'], $volta['stock']['40']['Preto']);
        $this->assertSame($original['stock']['41']['Preto'], $volta['stock']['41']['Preto']);
    }
}
