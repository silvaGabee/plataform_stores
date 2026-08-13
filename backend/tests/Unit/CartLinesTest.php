<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Normalização do carrinho.
 *
 * O carrinho chega de dois lugares com formatos diferentes: do sessionStorage
 * do navegador, como mapa "chave => quantidade" (onde a chave é "12" ou
 * "12#Preto|40"), e da API, como lista de objetos. Tudo que lê carrinho depende
 * dessa normalização — inclusive o cálculo do total do pedido.
 */
final class CartLinesTest extends TestCase
{
    public function testMapaSimplesDeProdutoParaQuantidade(): void
    {
        $linhas = store_cart_normalize_lines(['12' => 3]);

        $this->assertCount(1, $linhas);
        $this->assertSame(12, $linhas[0]['product_id']);
        $this->assertSame(3, $linhas[0]['quantity']);
        $this->assertNull($linhas[0]['variant_key']);
    }

    public function testChaveComVariacaoEhSeparadaNoPrimeiroCerquilha(): void
    {
        // A chave de variação contém "|" e pode conter praticamente qualquer
        // coisa; só o PRIMEIRO "#" separa o id.
        $linhas = store_cart_normalize_lines(['12#Preto|40' => 2]);

        $this->assertSame(12, $linhas[0]['product_id']);
        $this->assertSame('Preto|40', $linhas[0]['variant_key']);
    }

    public function testFormatoDeListaComObjetos(): void
    {
        $linhas = store_cart_normalize_lines([
            ['product_id' => 7, 'quantity' => 1, 'variant_key' => 'Azul|M'],
        ]);

        $this->assertSame(7, $linhas[0]['product_id']);
        $this->assertSame('Azul|M', $linhas[0]['variant_key']);
    }

    public function testQuantidadeZeroOuNegativaEhDescartada(): void
    {
        // Importante: uma linha com quantidade 0 não pode virar item de pedido.
        $this->assertCount(0, store_cart_normalize_lines(['12' => 0]));
        $this->assertCount(0, store_cart_normalize_lines(['12' => -5]));
        $this->assertCount(0, store_cart_normalize_lines([['product_id' => 9, 'quantity' => 0]]));
    }

    public function testValorNaoNumericoEhDescartado(): void
    {
        // O carrinho vem de JSON enviado pelo cliente: nada garante o tipo.
        $this->assertCount(0, store_cart_normalize_lines(['12' => 'abc']));
        $this->assertCount(0, store_cart_normalize_lines(['12' => null]));
    }

    public function testVariacaoVaziaViraNull(): void
    {
        $linhas = store_cart_normalize_lines(['12#' => 1]);
        $this->assertNull($linhas[0]['variant_key']);

        $linhas = store_cart_normalize_lines([['product_id' => 1, 'quantity' => 1, 'variant_key' => '  ']]);
        $this->assertNull($linhas[0]['variant_key']);
    }

    public function testChaveDeArmazenamentoEhSimetricaAoParse(): void
    {
        // A chave gerada precisa voltar a ser o mesmo par ao ser normalizada,
        // senão o item somado no carrinho não é o mesmo que sai no pedido.
        $chave = store_cart_line_storage_key(12, 'Preto|40');
        $this->assertSame('12#Preto|40', $chave);

        $linhas = store_cart_normalize_lines([$chave => 1]);
        $this->assertSame(12, $linhas[0]['product_id']);
        $this->assertSame('Preto|40', $linhas[0]['variant_key']);
    }

    public function testChaveSemVariacaoEhSoOId(): void
    {
        $this->assertSame('12', store_cart_line_storage_key(12, null));
        $this->assertSame('12', store_cart_line_storage_key(12, '   '));
    }
}
