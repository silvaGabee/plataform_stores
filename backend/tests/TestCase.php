<?php

namespace Tests;

/**
 * Base de teste mínima, sem dependências.
 *
 * O projeto não exige Composer para rodar (o deploy é copiar a pasta), e nem
 * toda máquina onde ele vive tem o binário instalado. Um PHPUnit que ninguém
 * consegue executar não é rede de segurança nenhuma — então a suíte roda com
 * `php backend/tests/run.php` e mais nada.
 *
 * A API imita a do PHPUnit de propósito: assertSame, assertTrue, expectException.
 * Quando o Composer entrar no fluxo, migrar é trocar a classe-base.
 */
abstract class TestCase
{
    /** @var list<array{nome: string, ok: bool, erro: string}> */
    public array $resultados = [];

    private string $testeAtual = '';

    /** Roda todo método público que comece com "test". */
    public function executar(): void
    {
        foreach (get_class_methods($this) as $metodo) {
            if (strncmp($metodo, 'test', 4) !== 0) {
                continue;
            }
            $this->testeAtual = $metodo;
            // Uma asserção que falha lança, então o primeiro erro encerra o
            // método — cada teste produz exatamente um resultado.
            try {
                $this->setUp();
                $this->$metodo();
                $this->resultados[] = ['nome' => $this->rotulo(), 'ok' => true, 'erro' => ''];
            } catch (\Throwable $e) {
                $this->resultados[] = ['nome' => $this->rotulo(), 'ok' => false, 'erro' => $e->getMessage()];
            }
        }
    }

    protected function setUp(): void
    {
    }

    private function rotulo(): string
    {
        $classe = (new \ReflectionClass($this))->getShortName();

        return $classe . '::' . $this->testeAtual;
    }

    // ------------------------------------------------------------ asserções

    protected function assertSame($esperado, $real, string $msg = ''): void
    {
        if ($esperado !== $real) {
            throw new \RuntimeException(
                ($msg !== '' ? $msg . ' — ' : '')
                . 'esperado ' . $this->descrever($esperado) . ', obtido ' . $this->descrever($real)
            );
        }
    }

    protected function assertEquals($esperado, $real, string $msg = ''): void
    {
        if ($esperado != $real) {
            throw new \RuntimeException(
                ($msg !== '' ? $msg . ' — ' : '')
                . 'esperado ' . $this->descrever($esperado) . ', obtido ' . $this->descrever($real)
            );
        }
    }

    protected function assertTrue($valor, string $msg = ''): void
    {
        $this->assertSame(true, $valor, $msg !== '' ? $msg : 'esperava true');
    }

    protected function assertFalse($valor, string $msg = ''): void
    {
        $this->assertSame(false, $valor, $msg !== '' ? $msg : 'esperava false');
    }

    protected function assertNull($valor, string $msg = ''): void
    {
        $this->assertSame(null, $valor, $msg !== '' ? $msg : 'esperava null');
    }

    protected function assertCount(int $esperado, $lista, string $msg = ''): void
    {
        $this->assertSame($esperado, is_countable($lista) ? count($lista) : -1, $msg);
    }

    /** Executa $fn e exige que ela lance a exceção informada. */
    protected function expectException(string $classe, callable $fn, string $msg = ''): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!($e instanceof $classe)) {
                throw new \RuntimeException(
                    ($msg !== '' ? $msg . ' — ' : '') . 'esperava ' . $classe . ', veio ' . get_class($e) . ': ' . $e->getMessage()
                );
            }

            return $e;
        }
        throw new \RuntimeException(($msg !== '' ? $msg . ' — ' : '') . 'esperava ' . $classe . ', nada foi lançado');
    }

    private function descrever($valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        if ($valor === null) {
            return 'null';
        }
        if (is_array($valor)) {
            return 'array' . json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_scalar($valor) ? var_export($valor, true) : gettype($valor);
    }
}
