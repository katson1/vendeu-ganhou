# Vendeu, Ganhou

O Vendeu, Ganhou é uma aplicação pequena para campanhas de incentivo. O administrador cadastra produtos, cria campanhas e registra vendas. Quando uma venda é aprovada, o seller recebe pontos; se ela for cancelada, os pontos são estornados.

Não há saque nem movimentação financeira. A carteira guarda apenas pontos.

## Rodando localmente

Você precisa de Docker Desktop com Docker Compose v2. As portas `5173`, `8080` e `13306` precisam estar livres.

Na raiz do projeto:

```bash
docker compose --env-file .env.example up --build
```

Depois acesse:

- Frontend: http://localhost:5173
- API: http://localhost:8080
- Healthcheck: http://localhost:8080/health
- MySQL: `127.0.0.1:13306`

O banco é criado e populado pelo MySQL na primeira subida, quando o volume ainda não existe. Para começar novamente do zero:

```bash
docker compose --env-file .env.example down -v
docker compose --env-file .env.example up --build
```

O comando `down -v` apaga o volume local do banco. Ele é útil para testes limpos, mas não deve ser usado se você quiser preservar os dados.

## Usuários do seed

Estas contas são destinadas ao ambiente local:

| Papel | Email | Senha |
| --- | --- | --- |
| Admin | `admin@vendeu.local` | `admin-local-2026` |
| Seller | `seller1@vendeu.local` | `seller-local-2026` |
| Seller | `seller2@vendeu.local` | `seller-local-2026` |

As senhas ficam no banco somente como bcrypt hash. Se você já tinha um volume criado com uma versão anterior do seed, faça o reset acima para carregar essas contas.

## Como o projeto está organizado

```text
backend/        API em PHP 8.3, PDO e JWT
frontend/       React + Vite
database/init/  schema e dados iniciais do MySQL
docker-compose.yml
```

O backend tem um router simples, middleware de autenticação/ACL, controllers, services e repositories. O frontend usa a History API do navegador e não depende de uma biblioteca de roteamento.

## Configuração

O arquivo `.env.example` já contém valores para rodar localmente. Se preferir, copie-o para `.env` e ajuste os valores.

| Variável | Valor local | Para que serve |
| --- | --- | --- |
| `COMPOSE_PROJECT_NAME` | `vendeu-ganhou` | Nome do projeto Docker |
| `APP_ENV` | `local` | Ambiente da aplicação |
| `JWT_SECRET` | `local-only-change-this-jwt-secret-before-production` | Chave de assinatura do JWT |
| `JWT_TTL` | `3600` | Validade do token, em segundos |
| `MYSQL_ROOT_PASSWORD` | `root_local_password` | Senha root do MySQL |
| `MYSQL_DATABASE` | `vendeu_ganhou` | Nome do banco |
| `MYSQL_USER` | `app` | Usuário da aplicação |
| `MYSQL_PASSWORD` | `app_local_password` | Senha da aplicação |
| `MYSQL_PORT` | `13306` | Porta publicada do MySQL |
| `BACKEND_PORT` | `8080` | Porta publicada da API |
| `FRONTEND_PORT` | `5173` | Porta publicada do frontend |
| `VITE_API_URL` | `http://localhost:8080` | URL da API no frontend |

Os valores acima são para desenvolvimento. Em produção, use um segredo JWT e credenciais de banco próprios, fora do repositório.

## Banco e regras principais

O schema cria as tabelas `users`, `products`, `campaigns`, `sales` e `wallet_entries`, com foreign keys, índices e constraints de unicidade. O seed inclui um admin, dois sellers, três produtos e uma campanha ativa.

O saldo não é salvo em uma coluna separada. Ele é calculado pelo ledger:

```text
créditos - débitos
```

Ao registrar uma venda, o backend faz na mesma transação:

1. valida os dados e carrega seller, produto e campanha;
2. bloqueia a campanha com `SELECT ... FOR UPDATE`;
3. verifica o budget disponível;
4. cria a venda e o crédito no ledger;
5. atualiza `budget_used`.

Se não houver pontos suficientes no budget, a venda inteira é rejeitada. Não existe crédito parcial. `external_id` é único, então repetir a mesma venda é idempotente. O cancelamento cria um débito, marca a venda como `canceled` e devolve o budget; repetir o cancelamento não duplica esses lançamentos.

## Frontend

- `/login`: login e redirecionamento por papel;
- `/admin`: produtos, campanhas, registro e cancelamento de vendas;
- `/seller`: saldo e extrato da própria carteira.

Sessões expiradas voltam para o login. Se o usuário tentar uma área que não corresponde ao seu papel, a aplicação informa que o acesso não é permitido.

## API

As respostas são JSON. Em caso de erro, o formato é:

```json
{
  "error": "codigo_do_erro",
  "message": "Descrição do problema"
}
```

Nas rotas protegidas, envie:

```http
Authorization: Bearer <token>
```

### Acesso

| Método | Rota | Quem pode usar | Observação |
| --- | --- | --- | --- |
| `GET` | `/health` | qualquer pessoa | Healthcheck |
| `POST` | `/auth/login` | qualquer pessoa | Recebe `email` e `password` |
| `GET` | `/me` | usuário autenticado | Retorna identidade e papel |
| `GET` | `/admin/health` | admin | Endpoint simples para testar ACL |

### Produtos

Todas as rotas abaixo são exclusivas do admin:

```text
GET    /products
POST   /products
PUT    /products/{id}
DELETE /products/{id}
```

O `DELETE` desativa o produto em vez de removê-lo do histórico. `name` e `sku` são obrigatórios na criação; `points_per_unit` deve ser um inteiro positivo e o SKU não pode se repetir.

### Campanhas

Também são rotas de admin:

```text
GET  /campaigns
POST /campaigns
```

Na criação, envie `name`, `budget_total`, `starts_at` e `ends_at`. As datas usam o formato `Y-m-d H:i:s`. A listagem inclui `budget_used` e `budget_remaining`.

### Vendas

Para registrar uma venda:

```http
POST /sales
```

Body:

```json
{
  "external_id": "pedido-1001",
  "campaign_id": 1,
  "seller_id": 2,
  "product_id": 1,
  "quantity": 2,
  "unit_value": "49.90"
}
```

Os pontos são `quantity * points_per_unit`. Para cancelar:

```http
POST /sales/{external_id}/cancel
```

As duas rotas são exclusivas do admin.

### Carteira

```http
GET /me/wallet
```

A rota é exclusiva do seller e retorna `balance` e `entries`. Cada lançamento informa `type` (`credit` ou `debit`), `points`, `description`, `campaign_id`, `sale_id` e `created_at`. O seller identificado no JWT é o único usado na consulta.

## Requests de exemplo

[requests.http](requests.http) contém exemplos para login, produtos, campanhas, vendas, cancelamento, wallet e ACL. O arquivo funciona no VS Code REST Client e no JetBrains HTTP Client. Depois do login, copie os tokens retornados para as variáveis do arquivo. Em um banco limpo, os IDs do seed são seller `2`, produtos `1` a `3` e campanha `1`.

## Testes

A imagem normal do backend não leva PHPUnit. Para rodar os testes, use o profile separado:

```bash
docker compose --env-file .env.example --profile test run --rm backend-test
```

A suíte cobre autenticação, JWT inválido, ACL, produtos, campanhas, scoring, idempotência, budget, cancelamento e isolamento da wallet.

Para conferir o ambiente manualmente:

```bash
docker compose --env-file .env.example ps
docker compose --env-file .env.example config --quiet
```

## Fora do escopo atual

O projeto não tem paginação, filtros, importação CSV ou trilha de auditoria detalhada. Também não há uma rota para listar sellers ou um histórico administrativo de vendas; por isso o admin informa o `seller_id` ao registrar uma venda e a tela mostra o último resultado processado.
