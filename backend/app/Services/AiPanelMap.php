<?php

namespace App\Services;

use App\Auth\Permissions;

/**
 * Mapa do painel para o assistente de IA: onde fica cada coisa, com o link.
 *
 * A IA não conhece as telas e, sem isto, inventava menus ("Inventário →
 * Reorganizar estoque"). Aqui está o que existe de verdade, com os nomes que
 * aparecem na tela. Ao mudar uma tela, atualize a entrada dela.
 *
 * Os links são montados com base_url() e o slug da loja; são os únicos que a
 * resposta pode conter (AiGuard::filtrarLinks remove o resto). Páginas que o
 * usuário não pode abrir entram sem link, marcadas como do gerente.
 */
final class AiPanelMap
{
    /**
     * caminho (depois de painel/{slug}), nome no menu, permissão da rota, o que tem lá.
     * Âncoras (#id) levam direto à seção quando ela tem id na view.
     */
    private const PAGINAS = [
        ['', 'Dashboard', 'store.panel.view',
            'Resumo do dia; cartão "Endereço público da vitrine" (botão Copiar); "Categorias da vitrine" (Adicionar categoria) [#dashboard-vitrine-categories-section]; '
            . '"Banner da vitrine" (Escolher imagem, Editar, Salvar na vitrine, Remover banner; ideal 1920×512 px) [#dashboard-banner-section]; '
            . 'alerta "Estoque baixo"; "Chave PIX da loja" (Tipo da chave, Chave PIX, Salvar chave PIX) [#pix-card] e "Pagamentos PIX pendentes" (Confirmar PIX) [#pending-payments] — estas duas só o gerente vê.'],
        ['produtos', 'Produtos', 'store.catalog.read',
            'Botão "Novo produto"; busca e filtro (Em promoção, Estoque baixo ou esgotado, Sem foto); clicar no produto abre "Editar produto": Informações (Nome, Descrição, Categorias da vitrine), '
            . 'Fotos (Adicionar fotos, Definir capa), Preços e estoque (Preço de custo, Preço de venda, Estoque inicial, Estoque mínimo, Código de barras ou SKU), Variações (cores, tamanhos, estoque por cor e tamanho). '
            . 'Só o gerente cria e edita. Não há excluir aqui: excluir fica em Estoque.'],
        ['estoque', 'Estoque', 'store.catalog.read',
            'Tabela com quantidade, mínimo e status de cada produto; painel "Avisos de estoque" (cores e tamanhos no mínimo ou zerados); '
            . 'botão "Ajustar" abre "Ajustar estoque" (Entrada, Saída ou Ajuste; Cor, Tamanho, Quantidade, Motivo) e tem "Excluir produto do catálogo". Ajustar e excluir são do gerente.'],
        ['historico-estoque', 'Histórico de estoque', 'store.stock.history',
            'Todas as movimentações (entradas, vendas, ajustes, devoluções) com filtros por tipo, responsável e datas; botão "Exportar CSV".'],
        ['entregas', 'Entregas', 'store.orders.read',
            'Pedidos da vitrine: "Aguardando pagamento" (Confirmar PIX recebido, Recebi no cartão, Cancelar pedido — só gerente); quadro "Retirada na loja" (Marcar como retirado); '
            . 'quadro "Entrega no endereço" (Solicitado → Empacotando → Na transportadora → Em rota → Entregue; botão Avançar e código de rastreio).'],
        ['pdv', 'PDV', 'store.pdv.operate',
            'Frente de caixa: Abrir caixa, Suprimento, Sangria, Fechar caixa; vender pelo catálogo ou código de barras; Adicionar desconto (de funcionário vai para aprovação do gerente); '
            . 'Finalizar venda com Dinheiro, Cartão ou PIX; Reimprimir cupom.'],
        ['promocoes', 'Promoções', 'store.promotions.manage',
            'Tela "Promoções e pagamentos", com abas: "Produtos em promoção" (preço promocional por produto), "Cupons" (Criar cupom), "Parcelamento e PIX" (regra de parcelas e Desconto no PIX), '
            . '"Frete" (faixas de CEP, Frete grátis a partir de um valor, Testar CEP), "Descontos do PDV" (histórico de descontos pedidos).'],
        ['vendas', 'Vendas', 'store.orders.refund',
            'Tela "Vendas e estornos": lista de vendas com busca por número ou cliente; botão "Estornar" (motivo e se as peças voltam ao estoque).'],
        ['clientes', 'Clientes', 'store.customers.read',
            'Clientes com compra, total comprado, ticket médio, última compra; filtros "Quem mais compra" e "Sumidos"; botão "Criar cupom".'],
        ['avaliacoes', 'Avaliações', 'store.reviews.manage',
            'Avaliações dos clientes: Responder, Remover da vitrine ou Restaurar; filtros A responder e 1 e 2 estrelas.'],
        ['metas', 'Metas', 'store.goals.read',
            'Meta do mês da loja e de cada funcionário: vendido, atingido, falta vender. O gerente define a meta ("Meta do mês", Salvar meta); o funcionário vê a sua.'],
        ['funcionarios', 'Funcionários', 'store.users.manage',
            'Equipe: "Novo funcionário" (Nome, E-mail, Senha, Tipo Funcionário ou Gerente, Cargo), Editar e Excluir funcionário.'],
        ['hierarquia', 'Hierarquia', 'store.roles.read',
            'Cargos e organograma: Novo cargo, a quem cada cargo responde, quem ocupa cada cargo (o gerente edita; o funcionário só vê).'],
        ['analyzing-bi', 'Analyzing BI', 'store.bi.read',
            'Indicadores e gráficos em grupos: Visão geral (vendido, lucro, margem, markup, pedidos, ticket médio, meta) [#bi-grupo-geral]; Vendas (faturamento ao longo do tempo, lucro por produto) [#bi-grupo-vendas]; '
            . 'Produtos (mais e menos vendido, parados, estoque crítico, Curva ABC) [#bi-grupo-produtos]; Pagamentos e cupons (PIX × parcelamento, cupons) [#bi-grupo-pagamentos]; Estoque (capital em estoque, giro e cobertura) [#bi-grupo-estoque]; '
            . 'Decisões (simulador "Vale a pena comprar este estoque?" com VPL, TIR, TMA, payback e financiamento Price × SAC; ideias de investimento) [#bi-grupo-decisoes]. Cada indicador tem um botão "!" com a explicação.'],
        ['configuracoes', 'Configurações', 'store.settings.write',
            'Seções: "Nome da loja" [#config-store-name-section]; "Slogan" [#config-store-slogan-section]; "WhatsApp da loja" [#config-store-whatsapp-section]; '
            . '"Dados da empresa no rodapé" (Razão social, CNPJ, Endereço) [#config-store-legal-section]; "Cor de fundo da vitrine" [#config-store-background-section]; '
            . '"Aparência da vitrine" (Cor das categorias, Cor do fundo do banner) [#config-store-appearance-section]; '
            . '"Foto da loja" — foto/ícone/logo da loja que aparece na aba do navegador, na vitrine e no painel (Escolher imagem, depois Alterar foto; quadrada, JPG, PNG, WebP ou ICO) [#config-store-photo-section]; '
            . '"Excluir loja" (pede a senha).'],
    ];

    /**
     * Páginas com link, conforme o que o usuário logado pode abrir.
     *
     * @param callable(string): bool|null $pode decide a permissão (padrão: Permissions::can na loja)
     * @return list<array{nome: string, url: ?string, descricao: string}>
     */
    public static function paginas(string $slug, int $storeId, ?callable $pode = null): array
    {
        $pode = $pode ?? static fn (string $perm): bool => Permissions::can($perm, $storeId);
        $base = 'painel/' . rawurlencode($slug);
        $out = [];
        foreach (self::PAGINAS as [$caminho, $nome, $perm, $descricao]) {
            $out[] = [
                'nome' => $nome,
                'url' => $pode($perm) ? base_url($caminho === '' ? $base : $base . '/' . $caminho) : null,
                'descricao' => $descricao,
            ];
        }
        $out[] = [
            'nome' => 'Vitrine (loja pública)',
            'url' => base_url('loja/' . rawurlencode($slug)),
            'descricao' => 'A loja como o cliente vê. No painel, o link "Ver vitrine" fica no rodapé do menu lateral.',
        ];

        return $out;
    }

    /**
     * Bloco de texto que vai para a IA. A âncora vira link completo, para a
     * IA só precisar copiar.
     *
     * @param list<array{nome: string, url: ?string, descricao: string}> $paginas
     */
    public static function texto(array $paginas): string
    {
        $linhas = [];
        foreach ($paginas as $p) {
            $url = $p['url'];
            $desc = preg_replace_callback('/\s*\[#([\w-]+)\]/', static function (array $m) use ($url): string {
                return $url !== null ? ' (link direto: ' . $url . '#' . $m[1] . ')' : '';
            }, $p['descricao']) ?? $p['descricao'];
            $linhas[] = $url !== null
                ? '- ' . $p['nome'] . ' — ' . $url . "\n  " . $desc
                : '- ' . $p['nome'] . ' — SÓ O GERENTE ACESSA (sem link para esta pessoa)' . "\n  " . $desc;
        }

        return implode("\n", $linhas);
    }

    /**
     * @param list<array{nome: string, url: ?string, descricao: string}> $paginas
     * @return list<string>
     */
    public static function links(array $paginas): array
    {
        return array_values(array_filter(array_column($paginas, 'url')));
    }
}
