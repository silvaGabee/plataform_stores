<?php

namespace App\Http;

/**
 * Página 404 com o visual da plataforma.
 *
 * Antes cada lugar respondia "Loja não encontrada" em texto puro: sem título,
 * sem idioma, sem viewport (no celular abria com 980 px de largura) e sem um
 * caminho de volta. Quem chega por um link antigo precisa de uma saída.
 *
 * A 404 de dentro de uma loja (produto ou categoria que sumiu) usa o layout da
 * própria loja: ver StoreFrontController::naoEncontrado().
 */
final class NotFound
{
    /**
     * Responde 404 com a página padrão e encerra.
     *
     * @return never
     */
    public static function pagina(
        string $titulo = 'Página não encontrada',
        string $mensagem = 'O endereço pode ter mudado ou a página foi removida.',
        ?string $acaoUrl = null,
        string $acaoTexto = 'Ir para a página inicial'
    ): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        $not_found = [
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'acao_url' => $acaoUrl ?? base_url(),
            'acao_texto' => $acaoTexto,
        ];
        $title = $titulo;
        require PLATAFORM_BACKEND . '/views/errors/404.php';
        exit;
    }
}
