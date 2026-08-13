<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Integridade dos arquivos de view.
 *
 * Existe por causa de um estrago real: um script que inseria a tag do csrf.js
 * usou `\1` numa string comum do Python em vez de `\\1`. O `\1` virou o
 * CARACTERE de código 1 (SOH) em vez da referência ao trecho capturado, então
 * o `</head>` dos três layouts foi substituído por um byte de controle.
 *
 * O navegador não pode renderizar texto dentro do <head>, então movia esse byte
 * para o início do <body> e o desenhava como um quadradinho vazio no canto
 * superior esquerdo de TODAS as páginas. Nada quebrava, nenhum teste falhava e
 * nenhum lint reclamava — só um quadrado que ninguém sabia de onde vinha.
 */
final class ViewsIntegrityTest extends TestCase
{
    /** @return list<string> */
    private function views(): array
    {
        $dir = PLATAFORM_BACKEND . '/views';
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arquivo) {
            if (strtolower($arquivo->getExtension()) === 'php') {
                $out[] = $arquivo->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    public function testNenhumaViewTemByteDeControle(): void
    {
        // Tab, LF e CR são legítimos; o resto abaixo de 0x20 não tem o que
        // fazer num arquivo de template.
        foreach ($this->views() as $arquivo) {
            $conteudo = file_get_contents($arquivo);
            $achado = preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $conteudo, $m, PREG_OFFSET_CAPTURE);
            $this->assertSame(
                0,
                $achado,
                $achado
                    ? basename($arquivo) . ': byte 0x' . bin2hex($m[0][0]) . ' na posição ' . $m[0][1]
                    : ''
            );
        }
    }

    public function testNenhumaViewTemBom(): void
    {
        // Um BOM no meio de um include vira texto visível na página.
        foreach ($this->views() as $arquivo) {
            $inicio = file_get_contents($arquivo, false, null, 0, 3);
            $this->assertFalse(
                $inicio === "\xEF\xBB\xBF",
                basename($arquivo) . ' começa com BOM'
            );
        }
    }

    public function testLayoutsFechamHeadEBody(): void
    {
        $layouts = [
            PLATAFORM_BACKEND . '/views/layout.php',
            PLATAFORM_BACKEND . '/views/store/layout_store.php',
            PLATAFORM_BACKEND . '/views/panel/layout_panel.php',
        ];
        foreach ($layouts as $arquivo) {
            $html = file_get_contents($arquivo);
            $nome = basename($arquivo);
            foreach (['<head>', '</head>', '<body', '</body>', '</html>'] as $tag) {
                $this->assertSame(
                    1,
                    substr_count($html, $tag),
                    $nome . ' deveria ter exatamente um "' . $tag . '"'
                );
            }
        }
    }

    public function testLayoutsCarregamCsrfEMetaToken(): void
    {
        // Se a tag do csrf.js sumir de um layout, todo POST daquela área passa
        // a ser recusado por falta de token — e só se descobre clicando.
        $layouts = [
            PLATAFORM_BACKEND . '/views/layout.php',
            PLATAFORM_BACKEND . '/views/store/layout_store.php',
            PLATAFORM_BACKEND . '/views/panel/layout_panel.php',
        ];
        foreach ($layouts as $arquivo) {
            $html = file_get_contents($arquivo);
            $nome = basename($arquivo);
            $this->assertTrue(str_contains($html, 'csrf_meta()'), $nome . ' sem csrf_meta()');
            $this->assertTrue(str_contains($html, "asset('js/csrf.js')"), $nome . ' sem o script csrf.js');
        }
    }
}
