<?php
/**
 * Política de privacidade e termos de uso.
 *
 * O cadastro não dizia o que era feito com os dados, e a LGPD (Lei
 * 13.709/2018) pede que a pessoa saiba disso antes de entregá-los. O texto
 * descreve o que o sistema realmente guarda; revise com quem responde
 * juridicamente pela plataforma antes de publicar.
 */
ob_start();
?>
<main class="page legal-page">
    <div class="container legal-inner">
        <p class="legal-eyebrow">Plataforma de Lojas</p>
        <h1 class="legal-title">Privacidade e termos de uso</h1>
        <p class="legal-lead">Este texto explica quais dados são coletados quando você cria uma conta ou compra em uma loja da plataforma, para que eles servem e como pedir a exclusão.</p>

        <nav class="legal-toc" aria-label="Nesta página">
            <a href="#dados">Dados coletados</a>
            <a href="#uso">Como usamos</a>
            <a href="#compartilhamento">Com quem compartilhamos</a>
            <a href="#direitos">Seus direitos</a>
            <a href="#cookies">Cookies</a>
            <a href="#termos">Termos de uso</a>
        </nav>

        <section id="dados" class="legal-section">
            <h2>Dados coletados</h2>
            <ul>
                <li><strong>Conta:</strong> nome, e-mail e senha. A senha é guardada criptografada (hash) e ninguém da plataforma ou das lojas consegue lê-la.</li>
                <li><strong>Compras:</strong> produtos, valores, forma de pagamento escolhida e situação de cada pedido.</li>
                <li><strong>Entrega:</strong> os endereços que você cadastrar.</li>
                <li><strong>Avaliações e favoritos:</strong> as notas e comentários que você publicar e os produtos que salvar.</li>
                <li><strong>Segurança:</strong> o endereço IP das tentativas de entrada, para bloquear quem tenta adivinhar senhas.</li>
            </ul>
            <p>Do cartão de crédito ficam guardados só o nome impresso, a bandeira e os 4 últimos dígitos, para identificar o pagamento. O número completo e o código de segurança nunca são guardados.</p>
        </section>

        <section id="uso" class="legal-section">
            <h2>Como usamos</h2>
            <ul>
                <li>Para identificar você ao entrar e manter seus pedidos, endereços e favoritos.</li>
                <li>Para que a loja prepare, cobre e entregue os pedidos.</li>
                <li>Para gerar os relatórios de vendas que cada loja vê no próprio painel.</li>
                <li>Para proteger a conta contra acesso indevido.</li>
            </ul>
            <p>Não vendemos seus dados e não enviamos propaganda sem o seu pedido.</p>
        </section>

        <section id="compartilhamento" class="legal-section">
            <h2>Com quem compartilhamos</h2>
            <p>Cada loja vê apenas os dados necessários para atender os pedidos feitos nela: seu nome, e-mail, endereço de entrega e os itens comprados. Uma loja não vê os pedidos que você fez em outra.</p>
        </section>

        <section id="direitos" class="legal-section">
            <h2>Seus direitos</h2>
            <p>Pela Lei Geral de Proteção de Dados (Lei 13.709/2018, art. 18), você pode confirmar quais dados temos, corrigi-los e pedir a exclusão. Na página <strong>Minha conta</strong> você altera seus dados e exclui a conta. Pedidos já feitos ficam guardados pelo tempo exigido pela legislação fiscal e de defesa do consumidor.</p>
        </section>

        <section id="cookies" class="legal-section">
            <h2>Cookies</h2>
            <p>Usamos apenas o cookie de sessão, que mantém você conectado e protege os formulários. O navegador também guarda, só no seu aparelho, o carrinho, o último CEP consultado e o tema claro ou escuro. Não usamos cookies de publicidade.</p>
        </section>

        <section id="termos" class="legal-section">
            <h2>Termos de uso</h2>
            <ul>
                <li>Cada loja é responsável pelos produtos que anuncia, pelos preços, pelo estoque, pela entrega e pelo atendimento.</li>
                <li>Nas compras pela internet, você pode desistir em até 7 dias após o recebimento (art. 49 do Código de Defesa do Consumidor). Fale com a loja para combinar a devolução.</li>
                <li>Mantenha sua senha em segredo. Você responde pelo que for feito com a sua conta.</li>
                <li>Não é permitido usar a plataforma para fraude, para publicar conteúdo ilegal ou para tentar acessar dados de outras pessoas.</li>
            </ul>
        </section>

        <p class="legal-updated">Atualizado em novembro de 2026.</p>
    </div>
</main>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
