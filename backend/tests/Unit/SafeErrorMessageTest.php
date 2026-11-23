<?php

namespace Tests\Unit;

use App\Controllers\Controller;
use Tests\TestCase;

/**
 * Mensagem de erro que pode ir para a tela.
 *
 * Regra de negócio fala com o usuário; erro de banco ou de programação vai
 * para o log e a tela recebe só a frase padrão — nunca SQL, nome de tabela
 * ou caminho de arquivo.
 */
final class SafeErrorMessageTest extends TestCase
{
    private function mensagem(\Throwable $e): string
    {
        $c = new class extends Controller {
            public function expor(\Throwable $e): string
            {
                return $this->mensagemSegura($e, 'Tente de novo.');
            }
        };

        return $c->expor($e);
    }

    public function testRegraDeNegocioPassa(): void
    {
        $this->assertSame('Estoque insuficiente.', $this->mensagem(new \InvalidArgumentException('Estoque insuficiente.')));
        $this->assertSame('Já existe um caixa aberto.', $this->mensagem(new \RuntimeException('Já existe um caixa aberto.')));
    }

    public function testErroDeBancoNaoVaza(): void
    {
        $sql = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'uniq_email'");
        $this->assertSame('Tente de novo.', $this->mensagem($sql));
    }

    public function testErroDeProgramacaoNaoVaza(): void
    {
        $this->assertSame('Tente de novo.', $this->mensagem(new \TypeError('trim(): Argument #1 ($string) must be of type string, array given')));
    }

    public function testCampoEnviadoComoListaViraTextoVazio(): void
    {
        $this->assertSame('', input_text(['x']));
        $this->assertSame('ana@x.com', input_text('  ana@x.com '));
        $this->assertSame('12', input_text(12));
    }
}
