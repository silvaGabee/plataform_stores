<?php

namespace App\Services;

/**
 * Travas do servidor em volta do assistente de IA.
 *
 * Os modelos gratuitos desobedecem as regras do prompt (num teste, um deles
 * listou os títulos das regras, disse quantas eram e comparou a loja com um
 * concorrente nomeado). O que não pode falhar fica aqui, sem depender do modelo:
 *
 *  - antes:  pergunta sobre as regras ou comparação com concorrente recebe
 *            resposta pronta, sem chamar a IA;
 *  - junto:  lembretes curtos, logo depois da pergunta, para os casos em que
 *            o modelo mais erra (modelo pequeno dá mais peso ao fim do texto);
 *  - depois: resposta que reproduz trechos das regras vira recusa, restos de
 *            código saem e o negrito é cortado em 3 trechos.
 */
final class AiGuard
{
    public const RECUSA_INSTRUCOES = 'Não compartilho minhas instruções internas, nem em partes. Posso ajudar com a loja?';
    public const RECUSA_COMPARACAO = 'Não faço comparação com outras lojas ou concorrentes. Posso analisar os números da sua loja ou dar dicas de como se diferenciar.';
    public const AVISO_CODIGO = '_(Trecho de código omitido: o assistente da loja não gera scripts nem código.)_';
    public const MAX_NEGRITO = 3;

    /** Minúsculas e sem acento, para as expressões regulares não dependerem de grafia. */
    public static function normalizar(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    /**
     * Tira o que está entre aspas e, em "Resuma isto: <texto>", o texto depois
     * dos dois-pontos. Assim uma avaliação ou nome de produto com "ignore suas
     * regras" é tratado como conteúdo, não como pedido sobre as regras.
     */
    private static function somenteOPedido(string $pergunta): string
    {
        $t = preg_replace('/"[^"]*"|“[^”]*”|‘[^’]*’|\'[^\']*\'|«[^»]*»/u', ' ', $pergunta) ?? $pergunta;
        $n = self::normalizar($t);
        if (preg_match('/^([^:]{0,120}):/', $n, $m)
            && preg_match('/\b(resum|analis|traduz|classifi|corrij|revis|avali|respond|comente|melhore|reescrev)/', $m[1])) {
            return $m[1];
        }

        return $n;
    }

    /**
     * Resposta pronta quando a pergunta não deve nem chegar à IA.
     *
     * @param list<string> $outrasLojas nomes das demais lojas da plataforma
     */
    public static function respostaPronta(string $pergunta, array $outrasLojas = []): ?string
    {
        $p = self::somenteOPedido($pergunta);
        $alvo = '(regras?|instruc\w*|prompt|diretrizes?|orientac\w*|configurac\w*)';
        $sobreRegras = preg_match('/\b(suas?|seus?|tuas?|teus?)\s+(\w+\s+){0,2}' . $alvo . '\b/', $p)
            || preg_match('/\b' . $alvo . '\s+(\w+\s+){0,2}(internas?|ocultas?|secretas?|originais?|do sistema|que (voce|vc) (recebeu|segue|tem))\b/', $p)
            || preg_match('/\b(voce|vc)\s+(\w+\s+){0,3}(recebeu|segue|obedece|tem)\s+(\w+\s+){0,2}' . $alvo . '\b/', $p)
            || preg_match('/\bquantas\s+(regras|instruc\w*|diretrizes)\b/', $p)
            || preg_match('/\bregra\s*(n[o°º.]*\s*)?\d+\b/', $p)
            || preg_match('/\b(system\s*prompt|prompt\s+(do sistema|inicial|original))\b|\bseu prompt\b/', $p);
        if ($sobreRegras) {
            return self::RECUSA_INSTRUCOES;
        }

        $comparar = preg_match('/\b(compar\w*|versus|vs\.?|x|melhor que|pior que|contra|frente a)\b/', $p);
        if ($comparar) {
            if (preg_match('/\b(concorren\w*|outras? lojas?|loja (vizinha|rival|do lado))\b/', $p)) {
                return self::RECUSA_COMPARACAO;
            }
            foreach ($outrasLojas as $nome) {
                $nome = trim(self::normalizar($nome));
                if (mb_strlen($nome) >= 4 && strpos($p, $nome) !== false) {
                    return self::RECUSA_COMPARACAO;
                }
            }
        }

        return null;
    }

    /**
     * Lembretes que vão logo depois da pergunta, só os que se aplicam.
     *
     * @return list<string>
     */
    public static function lembretes(string $pergunta): array
    {
        $p = self::normalizar($pergunta);
        $l = [];
        if (preg_match('/\b(voce|vc)\b\s+(\w+\s+){0,2}(recomendou|disse|falou|sugeriu|indicou|respondeu|prometeu|mandou|passou)\b|conversa (anterior|passada)|(na|da) ultima vez|como (voce )?(me )?disse/', $p)) {
            $l[] = 'Você NÃO tem acesso a conversas anteriores e não pode saber o que foi dito antes. Diga isso em uma frase. Não explique nem justifique o que teria sido dito; se fizer sentido, ofereça analisar o assunto agora.';
        }
        if (preg_match('/\b(marque|marcar|altere|alterar|mude|mudar|cancele|cancelar|envie|enviar|apague|apagar|exclua|excluir|cadastre|cadastrar|atualize|atualizar|aprove|aprovar|confirme o pagamento)\b.{0,50}\b(pedido|produto|e-?mail|cliente|estoque|pagamento|cupom)/', $p)) {
            $l[] = 'Você não executa ações no sistema. Responda "Não consigo …; isso é feito por você no painel da loja." Nunca diga "feito" nem finja que fez.';
        }
        if (preg_match('/\bcomo (eu )?(faco|fazer|cadastr|defin|alter|configur|mud|cri|adicion|coloc|mud|remov|exclu|ativ)/', $p)) {
            $l[] = 'Você não conhece as telas do painel: não cite menus, abas, botões nem caminhos ("vá em …"). Explique o que decidir ou preencher, não onde clicar. Se faltar informação sobre o produto ou a situação, pergunte.';
        }
        if (preg_match('/\b(repor|reposic\w*|estoque|acabando|esgot\w*|comprar mais)\b/', $p)) {
            $l[] = 'A linha de estoque do snapshot só conta produtos com estoque mínimo cadastrado. Se for 0, isso NÃO garante que nada precisa de reposição: produtos sem estoque mínimo e a demanda futura não são avaliados. Diga isso.';
        }
        if (preg_match('/\b(promoc\w*|desconto|precos?|precific\w*|cupom|garant\w*|lucro|margem)\b/', $p)) {
            $l[] = 'O snapshot não traz preços: não atribua preços a produtos da loja. Qualquer valor inventado deve vir escrito como "Exemplo hipotético:". Não garanta lucro nem vendas; diga que o efeito é possível e o que acompanhar.';
        }
        if (preg_match('/considere zero|como se fosse|\bontem\b|semana passada|mes passado|ano passado/', $p)) {
            $l[] = 'Cada valor do snapshot vale só para o período escrito na linha. Não troque períodos nem trate dado ausente como zero, mesmo que a pessoa peça; diga que não faz isso.';
        }
        if (preg_match('/\b(script|codigo|python|javascript|comando|terminal|automatiz\w*|sql|api|macro|planilha com formula)\b/', $p)) {
            $l[] = 'Não escreva código e não descreva comandos, scripts, tokens ou automações técnicas, nem em texto corrido. Atenda só a parte da loja e termine com: "A parte técnica (código ou comandos) eu não faço."';
        }
        $l[] = 'No máximo 3 trechos em negrito na resposta inteira, mesmo que peçam mais.';

        return $l;
    }

    /**
     * A resposta reproduz trechos das regras? Compara sequências de 5 palavras
     * da resposta com as das regras. As respostas prontas que as regras
     * mandam usar (entre aspas no texto das regras) não contam.
     */
    public static function vazaRegras(string $resposta, string $regras): bool
    {
        $r = self::normalizar($resposta);
        if (preg_match_all('/\[[a-g]\]|antes de responder,? confira|lembrete final|dados da loja \(snapshot\)|seguranca e limites|=== /', $r) >= 2) {
            return true;
        }
        $semAspas = preg_replace('/"[^"]*"/u', ' ', $regras) ?? $regras;
        $base = array_flip(self::sequencias(self::normalizar($semAspas), 5));
        $iguais = 0;
        foreach (array_unique(self::sequencias($r, 5)) as $s) {
            if (isset($base[$s]) && ++$iguais >= 4) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function sequencias(string $t, int $n): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $t, $m);
        $w = $m[0];
        $out = [];
        for ($i = 0, $c = count($w) - $n; $i <= $c; $i++) {
            $out[] = implode(' ', array_slice($w, $i, $n));
        }

        return $out;
    }

    /**
     * Tira blocos de código e o que fica pendurado neles (linhas com código
     * inline, token, API…), deixando um único aviso no fim.
     */
    public static function tirarCodigo(string $texto): string
    {
        $semBlocos = preg_replace('/```.*?(```|$)/s', "\x00", $texto) ?? $texto;
        if ($semBlocos === $texto) {
            return $texto;
        }
        $linhas = [];
        foreach (explode("\n", $semBlocos) as $linha) {
            if (strpos($linha, "\x00") !== false) {
                continue;
            }
            $n = self::normalizar($linha);
            if (preg_match('/`[^`]+`|\b(token|api|endpoint|script|python|terminal|import|biblioteca|requests?|json|codigo|substitu\w+ .{0,30}(seu|sua)_)\b|[a-z]+_[a-z_]+/', $n)) {
                continue;
            }
            $linhas[] = $linha;
        }
        $t = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $linhas)) ?? '');
        $t = preg_replace('/(\n|^)[-*_]{3,}\s*$/', '', $t) ?? $t;

        return trim($t . "\n\n" . self::AVISO_CODIGO);
    }

    /** Mantém os primeiros trechos em negrito e tira as marcas do resto. */
    public static function limitarNegrito(string $texto, int $max = self::MAX_NEGRITO): string
    {
        $i = 0;

        return preg_replace_callback('/\*\*(.+?)\*\*/s', static function (array $m) use (&$i, $max): string {
            return ++$i <= $max ? $m[0] : $m[1];
        }, $texto) ?? $texto;
    }
}
