# Plataforma de Lojas

Sistema web **multi-loja** em PHP: várias lojas independentes na mesma instalação, cada uma com **vitrine pública** (catálogo, carrinho, checkout), **painel administrativo** e **API JSON** consumida pelo JavaScript do painel e da loja.

O código segue uma organização em camadas simples (rotas, controllers, services, repositórios com PDO), **sem Composer** - o autoload de classes `App\` é feito em `bootstrap.php`.

---

### Visão geral

- **Uma instalação, N lojas:** cada loja tem nome, slug (URL), cidade, telefone, categoria.
- **Usuários:** o mesmo e-mail pode ter cadastros diferentes por loja. Tipos: **gerente** (dono da loja), **funcionário**, **cliente** (comprador na vitrine).
- **Plataforma:** login em `/`, lista em `/lojas` separando **Lojas que trabalho** (quem é gerente ou funcionário) e **Lojas disponíveis** (demais lojas). Cadastro de conta em `/criar-conta` e criação de nova loja em `/criar-loja`.

### Vitrine (`/loja/{slug}/...`)

Catálogo de produtos, página do produto, carrinho, checkout com pagamento (incluindo fluxo **PIX**), pedido confirmado. Suporte a **retirada na loja** ou **entrega**, com cadastro de **endereços** por e-mail quando necessário. Áreas **Meus pedidos** e **Meus endereços** para o cliente logado na loja.

### Painel (`/painel/{slug}/...`)

Acesso restrito conforme perfil. Inclui **dashboard** (link da loja, alertas, PIX pendente quando aplicável), **produtos**, **estoque**, **entregas** com quadro estilo Kanban (etapas de retirada e entrega), **PDV**, **funcionários**, **clientes**, **hierarquia** de cargos e **relatórios**.

### API (`/api/...`)

Endpoints REST usados pelo checkout, carrinho, painel (produtos, pedidos, usuários, PIX, estágios de entrega, etc.). As rotas estão em `backend/routes/api.php`.

### Integração PIX

Opcionalmente usa **RapidAPI** para geração de QR Code PIX (`RAPIDAPI_KEY` no `.env`, lido em `backend/config/app.php`). Sem chave, o comportamento do fluxo PIX depende da implementação atual.

---

## Requisitos técnicos

| Item | Sugestão |
|------|----------|
| PHP | 8.0+ com `pdo_mysql`, `json`, `session` |
| MySQL / MariaDB | 5.7+ |
| Apache | `mod_rewrite` ativo (ex.: XAMPP no Windows) |

---

## Como colocar para rodar

1. Coloque a pasta do projeto dentro de `htdocs` (ou equivalente).
2. Crie o banco executando **`backend/database/schema.sql`** no MySQL.
3. Rode os arquivos em **`backend/database/migrations/`** na ordem dos nomes, se o seu ambiente ainda não tiver essas colunas/tabelas (entregas, endereços, estágios, etc.).
4. Ajuste **`backend/config/database.php`** para host, nome do banco, usuário e senha do MySQL.
5. Ajuste **`url`** em **`backend/config/app.php`** para a URL pública da pasta `public` (ex.: `http://localhost/plataform_stores/public`).
6. Em **`public/.htaccess`** (e, se usar, **`frontend/public/.htaccess`**), o **`RewriteBase`** deve refletir o caminho depois da pasta `htdocs` (ex.: `/plataform_stores/public/`).
7. Opcional: arquivo **`.env`** na raiz com `RAPIDAPI_KEY=` para PIX via RapidAPI.
8. Acesse a URL do passo 5 no navegador.

---

## Estrutura de pastas

- **`backend/`** - PHP da aplicação: `app/` (controllers, services, repositórios, `Database`), `config/`, `routes/`, `views/`, `database/`, `bootstrap.php` (`.env` na raiz do projeto, autoload)
- **`frontend/public/`** - entrada real (`index.php`), `assets/` (CSS/JS), uploads de imagens, `.htaccess`
- **`public/`** - ponte: `index.php` inclui `frontend/public/index.php` para manter URLs `.../public/` no XAMPP sem reconfigurar o DocumentRoot

---

## Segurança e ambiente público

Em servidor real: use HTTPS, senha forte no MySQL, `debug` em `false` em `backend/config/app.php`, e não publique o `.env` nem credenciais no repositório.

---

## Sobre o código

Este repositório é um **projeto de aplicação web completo** para estudo ou uso próprio. O código é oferecido **como está**, sem garantia de adequação a um uso específico; quem implanta deve revisar segurança, backup e conformidade com a legislação aplicável (LGPD, meios de pagamento, etc.).
