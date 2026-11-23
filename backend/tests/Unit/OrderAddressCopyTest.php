<?php

namespace Tests\Unit;

use App\Services\OrderService;
use Tests\TestCase;

/**
 * Cópia do endereço no pedido.
 *
 * O pedido guarda o endereço da compra em JSON. Editar ou excluir o endereço
 * no cadastro do cliente depois não pode mudar para onde aquela compra vai.
 */
final class OrderAddressCopyTest extends TestCase
{
    private const ENDERECO = [
        'id' => 7,
        'user_id' => 3,
        'label' => 'Casa',
        'street' => 'Rua São João',
        'number' => '120',
        'complement' => null,
        'neighborhood' => 'Centro',
        'city' => 'Curitiba',
        'state' => 'PR',
        'zipcode' => '80010000',
    ];

    public function testCopiaGuardaSoOsCamposDaEntrega(): void
    {
        $copia = json_decode((string) OrderService::copiaDoEndereco(self::ENDERECO), true);

        $this->assertSame(['street', 'number', 'complement', 'neighborhood', 'city', 'state', 'zipcode'], array_keys($copia));
        $this->assertSame('Rua São João', $copia['street']);
        $this->assertNull($copia['complement']);
    }

    public function testSemEnderecoNaoHaCopia(): void
    {
        $this->assertNull(OrderService::copiaDoEndereco(null));
    }

    public function testPedidoUsaACopiaMesmoSemOCadastro(): void
    {
        // address_id nulo é o que sobra quando o cliente exclui o endereço.
        $pedido = ['address_id' => null, 'delivery_address' => OrderService::copiaDoEndereco(self::ENDERECO)];

        $e = OrderService::enderecoDoPedido($pedido);

        $this->assertSame('Curitiba', $e['city']);
        $this->assertSame('80010000', $e['zipcode']);
    }

    public function testPedidoSemCopiaESemCadastroNaoTemEndereco(): void
    {
        $this->assertNull(OrderService::enderecoDoPedido(['address_id' => null, 'delivery_address' => null]));
    }
}
