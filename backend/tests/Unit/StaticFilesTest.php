<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Contenção do servidor de arquivos estáticos.
 *
 * Testado aqui, e não por HTTP, porque o Apache normaliza ".." na URL antes de
 * o PHP ver — uma sonda com curl passa mesmo que o código não contenha nada.
 * O que importa é o comportamento da função quando o caminho traiçoeiro chega
 * até ela, o que acontece com outro servidor web ou com a URL codificada.
 */
final class StaticFilesTest extends TestCase
{
    private string $assets;

    protected function setUp(): void
    {
        require_once PLATAFORM_ROOT . '/frontend/public/static.php';
        $this->assets = PLATAFORM_ROOT . '/frontend/public/assets';
    }

    public function testServeArquivoQueExisteDentroDaPasta(): void
    {
        $real = static_resolve($this->assets, 'css/app.css');

        $this->assertTrue($real !== null, 'deveria resolver o CSS');
        $this->assertTrue(str_contains(str_replace('\\', '/', $real), '/assets/css/app.css'));
    }

    public function testRecusaEscaparComPontoPonto(): void
    {
        // Cada um destes resolveria para fora de assets/ se não houvesse
        // contenção — e o primeiro entregaria as credenciais do banco.
        $fugas = [
            '../../../backend/config/database.php',
            '../../../.env',
            '../../..',
            'css/../../../backend/bootstrap.php',
        ];
        foreach ($fugas as $fuga) {
            $this->assertNull(static_resolve($this->assets, $fuga), 'deveria recusar: ' . $fuga);
        }
    }

    public function testRecusaCaminhoAbsoluto(): void
    {
        $this->assertNull(static_resolve($this->assets, '/etc/passwd'));
        $this->assertNull(static_resolve($this->assets, 'C:/xampp/htdocs/plataform_stores/.env'));
    }

    public function testRecusaByteNuloEVazio(): void
    {
        // O byte nulo já truncou caminho em muita função de arquivo do PHP.
        $this->assertNull(static_resolve($this->assets, "css/app.css\0.png"));
        $this->assertNull(static_resolve($this->assets, ''));
    }

    public function testRecusaDiretorio(): void
    {
        $this->assertNull(static_resolve($this->assets, 'css'));
    }

    public function testApenasTiposConhecidosSaoServidos(): void
    {
        // Sem esta lista, um .php dentro de assets/ seria devolvido como texto
        // — ou pior, dependendo da configuração do servidor.
        $this->assertNull(static_mime('php'));
        $this->assertNull(static_mime('env'));
        $this->assertNull(static_mime('sql'));

        $this->assertSame('text/css; charset=utf-8', static_mime('css'));
        $this->assertSame('image/png', static_mime('png'));
        // A extensão pode vir em maiúsculas.
        $this->assertSame('image/png', static_mime('PNG'));
    }
}
