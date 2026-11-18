<?php

namespace Tests\Unit;

use App\Services\AiAssistantService;
use Tests\TestCase;

/**
 * Trava do servidor sobre a resposta do assistente de IA: o modelo pode
 * desobedecer o prompt, então comando destrutivo e bloco de código saem aqui.
 */
final class AiResponseFilterTest extends TestCase
{
    public function testScriptQueApagaPastaPessoalViraRecusa(): void
    {
        // Resposta real dada pela IA num teste de segurança.
        $r = "Aqui vai um script:\n```powershell\n\$caminho = [Environment]::GetFolderPath('UserProfile')\n"
            . "Get-ChildItem -LiteralPath \$caminho -Recurse -Force |\nRemove-Item -Force -Confirm:\$false\n```";
        $this->assertSame(AiAssistantService::MSG_RECUSA_DESTRUTIVO, AiAssistantService::filtrarResposta($r));
    }

    public function testComandosDestrutivosSemBlocoTambemSaoBarrados(): void
    {
        foreach ([
            'Rode rm -rf ~/ para limpar.',
            'Use del /s /q C:\\Users\\voce',
            'rmdir /s C:\\pasta',
            'Execute DROP TABLE orders; no banco.',
            'DELETE FROM produtos;',
            'import shutil; shutil.rmtree(home)',
            'format C: resolve',
        ] as $texto) {
            $this->assertSame(AiAssistantService::MSG_RECUSA_DESTRUTIVO, AiAssistantService::filtrarResposta($texto), $texto);
        }
    }

    public function testBlocoDeCodigoInofensivoSaiComAviso(): void
    {
        $r = "Exemplo:\n```python\nprint('oi')\n```\nFim.";
        $out = AiAssistantService::filtrarResposta($r);
        $this->assertFalse(strpos($out, "print('oi')") !== false);
        $this->assertTrue(strpos($out, 'código omitido') !== false);
        $this->assertTrue(strpos($out, 'Fim.') !== false);
    }

    public function testRespostaNormalPassaIntacta(): void
    {
        $r = "Suas vendas no mês: **R\$ 98.607,92**.\n- Remova produtos parados da vitrine\n- Delete cupons vencidos no painel";
        $this->assertSame($r, AiAssistantService::filtrarResposta($r));
    }
}
