# Auditoria técnica e plano de estruturação

**Projeto:** Plataforma de Lojas (PHP 8 + MySQL, sem Composer)
**Data:** 2026-08-05
**Escopo:** todo o repositório (98 arquivos PHP, 22 JS, schema + migrations)

---

## 0. Veredito

A base tem qualidades reais que muito projeto "profissional" não tem:

- **100% das queries são preparadas** (PDO com placeholders) — não achei um único ponto de SQL injection.
- **Separação em camadas coerente** (rotas → controller → service → repository) e respeitada na maior parte do código.
- **Views escapam com `htmlspecialchars`** de forma consistente; o carrinho re-resolve produtos do banco em vez de confiar no preço vindo do cliente.
- **Preços de pedido vêm sempre do banco** (`OrderService::createOrder`) — o furo clássico de e-commerce amador não existe aqui.

O problema não é o código linha a linha. É que **o sistema não tem um modelo de identidade nem um limite de confiança**. Quem pode fazer o quê está decidido de forma diferente em cada endpoint, às vezes só no HTML. Em cima disso, dinheiro é confirmado sem gateway e sem transação. É isso que separa este projeto de um produto.

Classifiquei tudo em P0 (dados de cliente / dinheiro em risco), P1 (integridade e operação) e P2 (arquitetura e manutenção).

---

## 1. P0 — Crítico

### 1.1 O checkout autentica pelo e-mail. Só pelo e-mail.

`backend/app/Controllers/Api/CheckoutApiController.php` — todos os 4 endpoints.

O comentário no topo da classe é explícito: *"APIs públicas do checkout (sem exigir login no painel)"*. Na prática, o e-mail é a credencial:

```
GET /api/loja/{slug}/checkout/addresses?email=vitima@gmail.com
```

Retorna rua, número, complemento, bairro, cidade, CEP da vítima. Sem sessão, sem token, sem nada. `PUT` e `DELETE` na mesma rota aceitam `{"email": "vitima@gmail.com"}` no corpo e sobrescrevem ou apagam o endereço dela.

E o `POST` (`createAddress:55-62`) **cria uma conta de usuário** para qualquer e-mail informado, com senha aleatória, sem confirmação. Dá para poluir a tabela `users` indefinidamente e sequestrar o vínculo e-mail↔loja antes que a pessoa real se cadastre.

Isso é vazamento de PII em massa, endereço residencial incluso — LGPD, artigo 46. É o achado mais grave do projeto.

**Mesmo padrão em:** `StoreFrontController::meusPedidos()` e `meusEnderecos()` (linhas 156-219) — aceitam `?email=` como identidade quando ninguém está logado.

### 1.2 Pagamento com cartão é auto-confirmado sem gateway nenhum

`backend/app/Controllers/Api/PaymentApiController.php:62-74` e `95-97`.

```php
if ($method === 'cartao' && $orderType !== 'pdv' && $deliveryType === 'entrega') {
    $cardMeta = card_validate_checkout_input(...);
    $autoConfirmCard = true;   // ← e logo depois: confirmPayment()
}
```

A única validação é `card_luhn_valid()` — checksum matemático. `4111 1111 1111 1111` passa. Qualquer pessoa finaliza um pedido "pago", **o estoque é baixado**, e o valor entra no faturamento do Analyzing BI como receita real. O lojista vê venda no dashboard, separa o produto e envia. Não existe adquirente, autorização, captura ou antifraude em lugar nenhum do código.

O PIX é mais honesto (confirmação manual pelo gerente), mas também não verifica nada com o banco — é confiança pura.

**A decisão aqui é de produto, não técnica:** ou integra um PSP de verdade (Mercado Pago / Stripe / Pagar.me), ou o cartão sai do fluxo e o pedido nasce `pendente` até confirmação manual. O que não pode continuar é o estado atual, que *parece* um pagamento.

### 1.3 `POST /api/store` cria loja + gerente sem autenticação

`StoreApiController::create():27-43`. Nenhum guard. Qualquer requisição anônima cria uma loja e um usuário `gerente` com a senha que quiser. É a única rota de escrita do sistema totalmente aberta — provavelmente sobrou de antes do fluxo `/criar-loja`, que exige login e confirmação de senha.

### 1.4 IDOR na página de pedido

`StoreFrontController::order():137-154`. `GET /loja/{slug}/pedido/{id}` valida só que o pedido pertence à *loja*. Não valida que pertence a *você*. Trocar o número na URL expõe nome do cliente, itens, valores, forma de pagamento, últimos 4 dígitos do cartão e o endereço de entrega completo. IDs sequenciais — dá para varrer a base inteira com um `for`.

### 1.5 `POST /api/loja/{slug}/payments` não verifica o dono do pedido

`PaymentApiController::create():40-49`. Para pedido `online` não há guard algum. Combinado com 1.2 e 1.4: dá para enumerar pedidos pendentes alheios e marcá-los como pagos com um cartão inventado.

### 1.6 `.env` e todo o código-fonte são servidos pelo Apache

O docroot é `C:\xampp\htdocs`. O projeto inteiro está dentro dele, e só existe `.htaccess` em `public/` e `frontend/public/`. Ou seja, são baixáveis por HTTP:

| URL | Conteúdo |
|---|---|
| `/plataform_stores/.env` | `RAPIDAPI_KEY`, `OPENROUTER_API_KEY` em texto puro |
| `/plataform_stores/backend/config/database.php` | credenciais do MySQL |
| `/plataform_stores/backend/database/schema.sql` | schema completo |
| `/plataform_stores/.git/` | histórico inteiro do repositório |
| `/plataform_stores/backend/tools/check_openrouter_env.php` | **executa** e gasta crédito da OpenRouter a cada acesso |

O `.gitignore` protege o `.env` do Git, o que é correto — mas não protege do servidor web. São coisas diferentes e só a primeira foi feita.

### 1.7 CSRF: a função existe, nunca é usada

`functions.php:70` define `csrf_token()`. `grep` no projeto inteiro: **zero chamadas**. Nenhum formulário emite o token, nenhum endpoint valida.

Como a autenticação é cookie de sessão puro, qualquer site consegue disparar em nome do usuário logado: `POST /minha-conta/excluir`, `POST /api/loja/{slug}/store/delete`, `POST /api/loja/{slug}/products/{id}/delete`. As rotas `PUT`/`DELETE` estão protegidas de fato por CORS/preflight, mas note que **quase toda operação destrutiva tem um alias `POST`** (`products/{id}/delete`, `users/delete`, `orders/{id}/entregas/delete`) — justamente as que passam sem preflight.

### 1.8 Sessão sem hardening e sem regeneração no login

`frontend/public/index.php:7` — `session_start()` cru. Sem `httponly`, sem `secure`, sem `samesite`. E `HomeController::login():43-45` grava na sessão existente sem `session_regenerate_id(true)` → fixação de sessão. Um `session_id` capturado antes do login continua válido depois.

Também não há rate limiting nem lockout em `/login`: força bruta ilimitada.

### 1.9 `debug => true` fixo no código, e o handler de erro vaza a exceção

`backend/config/app.php:7` — `'debug' => true`, hardcoded, não lido do `.env`. E `frontend/public/index.php:77-82`:

```php
} catch (\Throwable $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}
```

`$e->getMessage()` do PDO carrega trecho de SQL, nome de tabela e coluna. Vai direto para o cliente, independente do flag `debug`.

Pior: esse `try/catch` só existe no loop de rotas de API. As rotas web (linhas 88-97) não têm proteção nenhuma — exceção lá vira stack trace HTML na tela, com `display_errors` ligado.

---

## 2. P1 — Integridade de dados e operação

### 2.1 Nenhuma transação no fluxo de pedido e pagamento

`grep beginTransaction backend/` retorna **um único resultado** no projeto todo: `StoreApiController:397`, na exclusão de loja.

`OrderService::createOrder()` insere o pedido e depois os itens num loop. `OrderService::confirmPayment()` faz cinco escritas em sequência: status do pagamento, status do pedido, baixa de estoque por item, movimento de estoque, movimento de caixa. Qualquer falha no meio — deadlock, timeout, item inválido — deixa o banco num estado que ninguém consegue reconciliar: pedido pago com estoque não baixado, ou estoque baixado com caixa sem lançamento.

Toda escrita multi-tabela precisa de `beginTransaction`/`commit`/`rollBack`.

### 2.2 Venda a descoberto por corrida

A checagem de estoque está em `createOrder():66-69` e a baixa em `confirmPayment():150-156` — momentos diferentes, sem lock, sem transação. Dois compradores no último item: ambos passam na checagem, ambos confirmam, o estoque vai a negativo.

Mesmo em requisições simultâneas do mesmo instante, `confirmPayment` não é atômico: o `if ($payment['status'] === 'confirmado')` na linha 132 é um TOCTOU clássico — dois `confirm` concorrentes baixam o estoque duas vezes.

Correção: `SELECT ... FOR UPDATE` na linha de estoque dentro da transação, ou `UPDATE ... SET stock = stock - ? WHERE id = ? AND stock >= ?` verificando `rowCount()`.

### 2.3 O "funcionário só leitura" é só no HTML

`is_funcionario_panel_readonly()` existe e é chamada em exatamente **dois lugares**, ambos de apresentação: `PanelController` (esconde abas) e `AnalyzingBIController`.

Nenhuma API a consulta. Os endpoints de escrita usam `requireStorePanelAccess()`, que aceita `gerente` **ou** `funcionario`. Resultado: um funcionário com a aba escondida cria produto, apaga produto, ajusta estoque, apaga pedido de entregas — basta chamar a API direto. A restrição inteira é cosmética.

Isso é escalada de privilégio, e é o sintoma de um problema maior: **não existe um modelo de permissões**. Existem três checagens ad-hoc (`logged_in`, `is_gerente_store`, `can_access_store_panel`) espalhadas por 100+ endpoints, e cada um escolhe a sua na mão. Já há inconsistências visíveis — `getStoreIcon` exige gerente, mas `getBanner` aceita funcionário, sem razão aparente.

### 2.4 O `schema.sql` não instala o sistema

`backend/database/schema.sql` está dessincronizado das migrations. Faltam nele:

- tabela `user_addresses` (inteira)
- `orders.delivery_type`, `orders.address_id`, `orders.delivery_stage`, `orders.tracking_code`
- `stores.background_color`

Uma instalação nova seguindo o README roda o `schema.sql` e **o checkout quebra na primeira consulta**, porque `OrderRepository` referencia colunas que não existem. Só funciona quem aplicou as migrations na ordem certa, à mão, em algum momento do passado. Não é reproduzível.

O `schema.sql` também tem uma FK para `store_vitrine_categories` (linha 94) antes da tabela ser criada (linha 100) — só passa porque `FOREIGN_KEY_CHECKS = 0` está no topo.

### 2.5 Migrations sem runner, sem ordem, sem controle

13 arquivos `.sql` soltos numa pasta. Não há tabela de controle, não há ordenação (nomes sem timestamp), não há um comando que aplique. E são inconsistentes entre si:

- 5 são idempotentes (com `CREATE PROCEDURE` + `INFORMATION_SCHEMA`);
- 5 são `ALTER TABLE` cru — rodar duas vezes dá erro;
- as que usam `DELIMITER` **não podem ser executadas via PDO** — `DELIMITER` é diretiva do cliente `mysql`, não do servidor. Ou seja, um runner automático nunca vai conseguir aplicá-las como estão.

### 2.6 Uma pessoa é várias linhas na tabela `users`

Este é o problema estrutural mais profundo, e a raiz de vários outros.

O mesmo e-mail vira uma linha por loja (`StoreService::createStore():55-63` cria um novo `users` quando a pessoa já tem loja) mais uma linha "de plataforma" com `store_id = NULL`. O login (`HomeController::login():40-48`) itera **todas** as linhas do e-mail e entra na primeira cuja senha bate.

Consequências concretas:

1. **Senha não é uma só.** Trocar a senha numa loja não troca nas outras. Duas linhas do mesmo e-mail podem ter senhas diferentes, e você entra "como" a linha que casar — com permissões diferentes, sem saber qual.
2. **A ordenação é `ORDER BY store_id IS NULL DESC`**, ou seja, a conta de plataforma vem primeiro. Se as senhas coincidirem, você sempre loga como a conta sem loja — `is_gerente_store()` retorna `false` — e **perde acesso ao painel da sua própria loja** até acertar por acidente.
3. **`UNIQUE KEY unique_email_store (email, store_id)` não protege a conta de plataforma**, porque no MySQL `NULL` nunca colide com `NULL` num índice único. A checagem de duplicata está só no PHP (`createAccount():250`), sujeita a corrida. Nada impede duas contas de plataforma com o mesmo e-mail.
4. `detachUsersForDeletedStore()` (UserRepository:154-183) é uma máquina de estados de 30 linhas com `try/catch` em cima de violação de FK, existindo *só* para desfazer essa duplicação na hora de apagar a loja.

O modelo correto é `users` (identidade única, e-mail único global) + `store_members(user_id, store_id, role)`. Uma pessoa, uma senha, N vínculos.

### 2.7 A ordem do array de rotas é uma armadilha silenciosa

`backend/routes/api.php` é casado sequencialmente com regex. Hoje funciona porque `products/low-stock` (linha 55) está antes de `products/{id}` (56), e `orders/entregas` (68) antes de `orders/{id}` (69). Quem reordenar o array por estética — ou adicionar `orders/pendentes` no lugar errado — quebra a rota sem nenhum erro: `{id}` engole e o controller recebe `"entregas"` como `int $id`, que vira `0`.

O `Router::match()` também só reconhece parâmetros em minúsculas (`#\{[a-z]+\}#`): `{storeId}` nunca casa e a rota morre em silêncio.

---

## 3. P2 — Arquitetura e manutenção

### 3.1 `functions.php`: 52 KB, ~60 funções, um único arquivo

`backend/app/Helpers/functions.php` carrega em toda requisição e mistura, no mesmo namespace global:

- infra (`config`, `env`, `redirect`, `json_response`)
- autenticação e autorização (`logged_in`, `is_gerente_store`, `can_access_store_panel`)
- upload e I/O de arquivo (5 funções)
- **regra de negócio pesada** — todo o domínio de variação de produto: `product_variants_matrix_to_rows`, `product_apply_sale_stock_decrement`, `product_sale_available_stock`, `store_cart_normalize_lines` (~700 linhas)
- validação de cartão (Luhn, bandeira, expiração)
- helpers de view (`btn_icon_plus`, `favicon_url`, catálogo de ícones SVG inline)

`product_apply_sale_stock_decrement()` é a função que baixa estoque de venda — o coração transacional do sistema — e mora num arquivo de helpers, como função global, recebendo repositórios por parâmetro. Isso não é testável e não é substituível.

Todas estão embrulhadas em `if (!function_exists(...))`, o que é sintoma de um autoload que não dá garantia de carregamento único.

### 3.2 Sem Composer, sem testes, sem CI, sem versão mínima de PHP

Não existe `composer.json`. O autoload é um `spl_autoload_register` de 8 linhas em `bootstrap.php`. O código usa promoção de construtor e tipos de retorno de PHP 8, mas **nada declara que precisa de PHP 8** — num servidor com 7.4 o projeto morre com erro de parse, sem mensagem útil.

Zero testes. Zero CI. Zero linter. A garantia de qualidade hoje é abrir o navegador e clicar.

### 3.3 Sem logging

`grep error_log backend/` → nada. `storage/` está vazio (o `.gitignore` reserva `storage/logs/`, que não existe). Quando um pagamento falhar em produção, não haverá **nenhum** rastro para investigar. Num sistema que movimenta estoque e dinheiro, isso inviabiliza suporte e auditoria.

### 3.4 Injeção de dependência inexistente

`new StoreRepository()`, `new UserRepository()`, `new ProductService(...)` aparecem dentro de controllers e services por todo lado — `OrderApiController::orderService()` instancia 7 repositórios na mão a cada chamada. Os services até *aceitam* dependências no construtor (bom instinto), mas ninguém injeta: o chamador monta o grafo inteiro toda vez. Nada disso pode ser mockado.

### 3.5 Assets servidos pelo PHP, sem cache

`frontend/public/index.php:43-66` serve `/assets/*` com `readfile()`. Sem `Cache-Control`, sem `ETag`, sem `Last-Modified` — **o `app.css` de 301 KB é relido do disco e retransmitido a cada page view**. E, diferente do bloco de `/uploads` logo acima (linha 31), este **não faz checagem de `realpath()`** para conter o path dentro do diretório.

Além disso, cada upload tem duas URLs — via rewrite (`/public/uploads/...`, passa pelo PHP) e direta (`/frontend/public/uploads/...`, servida pelo Apache). Nenhuma das duas tem controle de acesso.

### 3.6 Front-end sem build

`app.css` com 301 KB num arquivo. `panel-products.js` com 53 KB, `panel-configuracoes.js` com 30 KB, `panel-stock.js` com 30 KB — todos globais, sem módulos, sem bundler, sem minificação. Fora `gifenc.js`/`omggif.js` vendorizados à mão, sem versão registrada.

### 3.7 `PixService`: cURL sem timeout e chave PIX indo para terceiro

`PixService::generateViaPixRapidApi():116-125` não define `CURLOPT_TIMEOUT` nem `CURLOPT_CONNECTTIMEOUT`. Se a RapidAPI travar, **o checkout do cliente fica pendurado** até o `max_execution_time` do PHP. (O `AiAssistantService` faz certo, com `CURL_TIMEOUT_SEC` — a inconsistência é o ponto.)

E o fallback (`generateViaFreeApi:106`) monta uma URL para `api.qrserver.com` **com a chave PIX do lojista embutida no query string** — dados financeiros de terceiros saindo para um serviço gratuito externo, registrados no log de acesso deles.

### 3.8 Código morto e duplicações

| Item | Situação |
|---|---|
| `backend/app/Models/Model.php` | classe abstrata sem uma única subclasse — a camada `Models/` foi abandonada em favor de `Repositories/`, mas o esqueleto ficou |
| `public/` vs `frontend/public/` | duas raízes, dois `.htaccess` idênticos, `public/index.php` é só um `require` da outra |
| `GET /api/{slug}/analyzing-bi` | alias duplicado de `/api/loja/{slug}/analyzing-bi`, ambos apontando ao mesmo handler |
| `DELETE .../{id}` + `POST .../{id}/delete` | pares duplicados em produtos, usuários e pedidos |
| `AnalyzingBIController` | 44 linhas que só existem para não caber no `PanelController` |

### 3.9 N+1 no carrinho e na vitrine

`_cart_items_build.php:19` chama `getByIdAndStore()` num loop, e cada chamada dispara consultas adicionais de imagens e variações. Carrinho de 10 itens = dezenas de round-trips. `CartApiController::sync()` ainda aceita e guarda na sessão um array de tamanho ilimitado vindo do cliente, sem validar forma nem quantidade.

---

## 4. Plano de estruturação

Ordenado por risco, não por dificuldade. As fases 0 e 1 são as que impedem o sistema de ir para produção hoje.

### Fase 0 — Estancar ✅ CONCLUÍDA (2026-08-05)

Nada aqui exigiu refatoração. Foi fechar buraco.

1. ✅ **Exposição de arquivos.** `.htaccess` na raiz + em `backend/`, `storage/` e `docs/`. Bloqueados: `.env`, `*.sql`, `*.md`, `.git/`, todo o código do servidor. `frontend/public/uploads/.htaccess` impede execução de PHP no que for enviado por usuários. O ideal continua sendo apontar o DocumentRoot para `frontend/public/` — o `.htaccess` é o que vale enquanto o projeto vive dentro de `htdocs`.
2. ✅ **Scripts de manutenção** (`check_openrouter_env.php`, `generate_category_icons.php`) com guarda `PHP_SAPI !== 'cli'`, além do `.htaccess`.
3. ✅ **`debug` vindo do `.env`** (`APP_DEBUG`), handler de erro único em `index.php` cobrindo API **e** rotas web. A mensagem da exceção não vai mais para o cliente: o usuário recebe um id (`ref`) e o stack trace vai para `storage/logs/`. Entrou junto um logger mínimo (`log_message`/`log_exception` em `functions.php`) — esconder o erro sem registrá-lo em lugar nenhum seria pior que o problema original.
4. ✅ **Checkout exige login.** Os 4 endpoints de `CheckoutApiController`, `meusPedidos`, `meusEnderecos` e `OrderApiController::create` derivam a identidade de `$_SESSION`. O parâmetro `email` foi removido do PHP, das views e do JS. `customer_id`/`customer_email` do corpo do pedido também: davam para lançar pedido no nome de outra pessoa.
5. ✅ **Acesso ao pedido por token.** Coluna `orders.access_token` (migration `add_order_access_token.sql`), link do comprovante com `?t=`, comparação com `hash_equals`. Três caminhos de acesso: equipe da loja, dono logado, ou portador do link. Resposta 404 (não 403) para quem não passa.
6. ✅ **`POST /api/loja/{slug}/payments`** e `GET .../payments/{id}/status` exigem posse do pedido.
7. ✅ **`POST /api/store` removida** — criava loja + gerente sem autenticação nenhuma, e nada no front a chamava.
8. ✅ **Hardening de sessão**: cookie `httponly`/`samesite=Lax`/`secure` sob HTTPS, `session_regenerate_id(true)` no login, no logout e ao criar loja. `logout()` passou a limpar carrinho e slug da loja.
9. ⏸️ **Pagamento com cartão — fora de escopo por decisão do proprietário** (2026-08-05): o sistema não vai a produção com pagamento real, o checkout é simulação. O `$autoConfirmCard` continua como está.

    Fica registrado o que isso implica enquanto for assim: qualquer número que passe no Luhn marca o pedido como pago, baixa estoque e entra como receita no Analyzing BI. Antes de qualquer uso real, este item volta a ser bloqueante.

**Correções de rota durante a implementação:** o `RedirectMatch` que eu tinha escrito para bloquear `backend|storage|docs` casava contra a URL inteira e derrubaria uma vitrine com slug `docs`; virou `.htaccess` por diretório. E a migration usava `RANDOM_BYTES()`, que só existe no MariaDB 10.10+ — o ambiente roda 10.4, então o backfill passou a usar `MD5(UUID()+RAND())` (os tokens novos vêm de `random_bytes()` no PHP, esses sim criptográficos).

### Fase 1 — Integridade ✅ CONCLUÍDA (2026-08-05)

9. ✅ **Transações** em `createOrder`, `addPayment`, `confirmPayment` e `ProductService::adjustStock`, via `Database::transaction()` — helper reentrante, porque os serviços se chamam entre si e o PDO não tem transação aninhada.
10. ✅ **Baixa de estoque atômica** em `ProductRepository::decrementStock` e `ProductVariantRepository::decrementStock`: `WHERE ... AND stock_quantity >= ?`, conferindo `rowCount() === 1`.

    O diagnóstico original estava incompleto. O `GREATEST(0, stock - ?)` que existia ali **aceitava vender mais do que havia**: com estoque 3 e venda de 5, o saldo ia a 0 e a operação era reportada como bem-sucedida — duas unidades saíam sem existir. E `product_apply_sale_stock_decrement()` devolvia `void`, então nem quando o banco recusava alguém ficava sabendo. Agora devolve `bool` e o `confirmPayment` lança se vier `false`.
11. ✅ **`SELECT ... FOR UPDATE`** na linha do pagamento (`PaymentRepository::findForUpdate`) e do pedido (`OrderRepository::findByIdAndStoreForUpdate`). Confirmação dupla e duplo clique no botão de pagar deixaram de duplicar.
12. ✅ **`schema.sql` consolidado** a partir do `mysqldump --no-data`, já registando as 13 migrations em `schema_migrations`. Uma instalação nova sai dele completa e em dia. Validado: banco vazio → `schema.sql` → `migrate --status` diz "0 pendentes" → compra ponta a ponta funciona.
13. ✅ **Runner de migrations** (`backend/scripts/migrate.php`) com `schema_migrations`, modos `--status` e `--baseline`, arquivos renomeados para `0001_`..`0013_` em ordem de dependência, e todo `DELIMITER`/`CREATE PROCEDURE` substituído por `PREPARE`/`EXECUTE` — que roda por PDO.
14. ✅ **Backup automático** (`App\Database\Backup` + `backend/scripts/backup.php`): o `migrate.php` gera um dump antes de aplicar qualquer DDL num banco com dados. DDL faz commit implícito no MySQL/MariaDB, então não existe rollback — o dump é a única forma de voltar atrás.
15. ✅ **Seed** (`backend/scripts/seed.php`): loja de exemplo com 7 produtos, 3 com matriz de cor/tamanho, um abaixo do estoque mínimo, três contas de acesso. Escrito em PHP para usar os helpers do domínio, de modo que a matriz de variações saia no formato exato que o painel espera.

**Verificação:** `backend/tools/concurrency_check.php` — 18 asserções contra o banco real, cobrindo venda a descoberto, rollback, transação aninhada, confirmação dupla, estoque que some entre pedido e confirmação, e duplo clique no pagamento. Cria e apaga os próprios dados.

> **Incidente durante esta fase.** Ao validar a instalação limpa, executei `DROP DATABASE plataform_stores` contra o banco de desenvolvimento real em vez do descartável que eu havia criado para isso. Com `log_bin=OFF` e sem dump anterior, os dados foram perdidos de forma definitiva — loja, produtos, usuários e configurações. Só as imagens em `frontend/public/uploads/` sobreviveram, por estarem em disco.
>
> O `seed.php` existe por causa disso, e o backup automático do item 14 é a resposta estrutural: nenhum script deste projeto altera schema sem dump antes.

### Fase 2 — Autorização de verdade (3–5 dias)

14. **Middleware no Router.** Rota passa a declarar sua exigência em vez de cada controller lembrar de chamar o guard:
    ```php
    'POST /api/loja/{slug}/products' => [ProductApiController::class, 'create', 'auth' => 'store:write'],
    ```
    Um pipeline central resolve `slug → store`, aplica CSRF, aplica o guard e só então chama o controller. Endpoint sem declaração explícita **nega por padrão** — hoje um `require` esquecido abre a rota em silêncio.
15. **Permissões nomeadas** (`store.products.write`, `store.payments.confirm`, `store.settings.write`) mapeadas a papéis, substituindo `is_gerente_store` / `can_access_store_panel` / `is_funcionario_panel_readonly` espalhados. O read-only do funcionário passa a valer na API, não no HTML. As tabelas `roles`/`employee_roles` já existem no schema e hoje são decorativas — é aqui que elas ganham função.
16. **CSRF** no mesmo pipeline: token no `<meta>`, header `X-CSRF-Token` no `fetch`, validação obrigatória em todo `POST`/`PUT`/`DELETE`. Aproveitar para remover os aliases `POST .../delete` e usar os verbos corretos.
17. **Rate limit** em `/login`, `/criar-conta` e nos endpoints de IA.

### Fase 3 — Identidade única (3–4 dias, uma migração de dados)

18. `users` passa a ter **`UNIQUE (email)` global**. Nova tabela `store_members (user_id, store_id, role, UNIQUE(user_id, store_id))`.
19. Migração: consolidar linhas duplicadas por e-mail numa só (mantendo o hash de senha mais recente), converter cada `store_id`/`user_type` em uma linha de `store_members`, repontar `orders.customer_id`, `orders.created_by`, `cash_registers.opened_by`, `employee_goals.user_id`, `user_addresses.user_id`.
20. Login volta a ser uma consulta única. `is_gerente_store` vira um `SELECT role FROM store_members`. `detachUsersForDeletedStore()` (30 linhas de heurística) é deletada — passa a ser `ON DELETE CASCADE` no vínculo.

Faça esta fase **depois** da 2, para não migrar dados e reescrever autorização ao mesmo tempo.

### Fase 4 — Fundação de engenharia (contínuo)

21. **`composer.json`** com `"require": {"php": "^8.1"}` e PSR-4 (`App\` → `backend/app/`). Mesmo sem dependências externas, isso trava a versão de PHP e mata o autoloader artesanal.
22. **Quebrar o `functions.php`**:
    - `App\Domain\Product\VariantMatrix` e `VariantStock` — toda a lógica de variação e baixa de estoque, como classes
    - `App\Domain\Cart\CartNormalizer`
    - `App\Payment\CardValidator`
    - `App\Storage\ImageUploader` (unificando as 5 funções de upload, que são cópias uma da outra)
    - `App\Support\ViewHelpers` — só o que é de view fica como função global
23. **PHPUnit** cobrindo, nesta ordem: baixa de estoque com variação, cálculo de total do pedido, confirmação de pagamento, matriz de permissões. São as quatro coisas que, quando quebram, custam dinheiro.
24. **PHPStan nível 5** + **PHP-CS-Fixer**, rodando em GitHub Actions no PR.
25. **Logging** (`App\Support\Logger` em `storage/logs/`, ou Monolog): toda exceção, toda confirmação de pagamento, toda mudança de permissão. Sem isso não há suporte possível.
26. **Container de DI simples** (ou factories) para parar de montar 7 repositórios na mão em cada request.

### Fase 5 — Performance e polimento

27. `Cache-Control` + `ETag` nos assets — ou, melhor, deixar o Apache servi-los direto e tirar o bloco de `readfile()` do `index.php`. Adicionar a checagem de `realpath()` que falta em `/assets`.
28. Bundler no front (Vite), quebrando `app.css` e os JS de painel em módulos por página.
29. Eliminar o N+1: `ProductService` com um `findManyByIds()` que traz produtos, imagens e variações em 3 queries, não em 3N. Validar tamanho e forma do carrinho em `CartApiController::sync`.
30. `CURLOPT_TIMEOUT` e `CURLOPT_CONNECTTIMEOUT` no `PixService`. Gerar o QR code localmente (a biblioteca de QR é trivial e o `buildPixPayload` já monta o BR Code correto) em vez de mandar a chave PIX do lojista para `api.qrserver.com`.
31. Limpeza: apagar `Models/Model.php`, unificar `public/` e `frontend/public/`, remover os aliases de rota duplicados, fundir `AnalyzingBIController` no `PanelController`.

---

## 5. Resumo executivo

| Fase | Entrega | Esforço | Risco que elimina |
|---|---|---|---|
| 0 ✅ | Estancar exposição | feita | Vazamento de PII, código-fonte e chaves de API públicos |
| 1 ✅ | Integridade de dados | feita | Venda a descoberto, estoque e financeiro inconsistentes, instalação nova quebrada |
| 2 | Autorização central | 3–5 dias | Escalada de privilégio; CSRF; rota nova nascer aberta |
| 3 | Identidade única | 3–4 dias | Login imprevisível; gerente perdendo o próprio painel |
| 4 | Fundação de engenharia | contínuo | Regressão silenciosa; impossibilidade de testar |
| 5 | Performance e limpeza | contínuo | Custo de infra; dívida acumulada |

**Próximo passo:** Fase 2 (autorização central). Hoje a restrição do funcionário é só interface — ele cria e apaga produto chamando a API direto — e não existe CSRF em lugar nenhum. É também a fase que faz endpoint novo nascer fechado por padrão, em vez de depender de alguém lembrar de chamar o guard.

**O que já está certo e não deve ser mexido:** as queries preparadas, o escape nas views, o preço vindo do banco no `OrderService`, e a separação controller/service/repository. A refatoração deve preservar esses quatro acertos — são a base sobre a qual o resto se apoia.
