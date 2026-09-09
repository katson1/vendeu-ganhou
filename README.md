# Vendeu, Ganhou

Aplicação enxuta de incentivo de vendas. O admin cadastra produtos, cria campanhas e registra vendas. Uma venda aprovada gera pontos para o seller; o cancelamento cria um débito no ledger e devolve os pontos ao budget da campanha.

Não existe saque nem movimentação de dinheiro real. A carteira é composta exclusivamente por pontos.

## Arquitetura

```text
frontend/       React + Vite, servido pelo Node dentro do Docker
backend/        PHP 8.3 puro, PDO, router e middleware próprios
database/init/  schema e seed executados pelo MySQL em volume vazio
docker-compose.yml
```

O backend mantém responsabilidades pequenas: controllers recebem requisições, services aplicam regras de negócio, repositories usam PDO prepared statements e middleware aplica autenticação/ACL. JWT carrega a identidade e o papel do usuário.

## Pré-requisitos

- Docker Desktop com Docker Compose v2;
- portas `8080`, `5173` e `13306` livres;
- nenhum framework PHP ou instalação local de Node é necessária.

## Como executar

Na raiz do projeto:

```bash
docker compose --env-file .env.example up --build
```

Endereços locais:

- Frontend: http://localhost:5173
- Backend: http://localhost:8080
- Healthcheck: http://localhost:8080/health
- MySQL: `127.0.0.1:13306`

O Compose inicializa o schema e o seed automaticamente quando o volume MySQL está vazio. Para recriar o banco do zero:

```bash
docker compose --env-file .env.example down -v
docker compose --env-file .env.example up --build
```

`down -v` remove o volume local do banco e todos os dados nele. Use apenas quando quiser um ambiente limpo.

## Credenciais seed

As credenciais abaixo são somente para desenvolvimento local:

| Papel | Email | Senha |
| --- | --- | --- |
| Admin | `admin@vendeu.local` | `admin-local-2026` |
| Seller 1 | `seller1@vendeu.local` | `seller-local-2026` |
| Seller 2 | `seller2@vendeu.local` | `seller-local-2026` |

As senhas são armazenadas apenas como bcrypt hash em `database/init/002_seed.sql`. Se o volume foi criado antes destas credenciais serem definidas, execute o reset do volume descrito acima.

## Variáveis de ambiente

Copie `.env.example` se quiser criar um arquivo `.env` próprio. Os valores abaixo são defaults locais e não devem ser usados em produção.

| Variável | Default | Uso |
| --- | --- | --- |
| `COMPOSE_PROJECT_NAME` | `vendeu-ganhou` | Nome do projeto Compose |
| `APP_ENV` | `local` | Ambiente da aplicação |
| `JWT_SECRET` | `local-only-change-this-jwt-secret-before-production` | Assinatura dos tokens |
| `JWT_TTL` | `3600` | Expiração do JWT em segundos |
| `MYSQL_ROOT_PASSWORD` | `root_local_password` | Senha root do MySQL |
| `MYSQL_DATABASE` | `vendeu_ganhou` | Banco da aplicação |
| `MYSQL_USER` | `app` | Usuário da aplicação |
| `MYSQL_PASSWORD` | `app_local_password` | Senha da aplicação |
| `MYSQL_PORT` | `13306` | Porta MySQL publicada |
| `BACKEND_PORT` | `8080` | Porta HTTP do backend |
| `FRONTEND_PORT` | `5173` | Porta HTTP do frontend |
| `VITE_API_URL` | `http://localhost:8080` | URL da API usada pelo frontend |

Em produção, altere principalmente `JWT_SECRET`, credenciais do banco e portas conforme o ambiente. O segredo JWT não deve ser commitado.

## Banco e regras de domínio

O schema contém `users`, `products`, `campaigns`, `sales` e `wallet_entries`, com chaves estrangeiras, índices e restrições de unicidade. O seed cria um admin, dois sellers, três produtos e uma campanha ativa.

O saldo nunca é armazenado em um campo mutável. Ele é calculado como:

```text
SUM(credits) - SUM(debits)
```

Ao aprovar uma venda, a transação cria a venda, cria o crédito e aumenta `budget_used`. A campanha é bloqueada com `SELECT ... FOR UPDATE` antes da validação do budget. Se o budget restante não comportar todos os pontos, a venda é rejeitada integralmente e nada parcial é persistido.

`external_id` é único. Repetir a mesma venda com os mesmos dados é idempotente; reutilizá-lo com dados diferentes retorna conflito. O cancelamento também é transacional: cria um único débito, marca a venda como `canceled` e reduz o budget uma única vez.

## Frontend

- `/login`: autenticação e redirecionamento por papel;
- `/admin`: produtos, campanhas, registro e cancelamento de vendas;
- `/seller`: saldo e extrato da própria carteira;
- rotas protegidas tratam sessão expirada com retorno ao login e bloqueio de papel com resposta 403.

O admin informa o ID do seller ao registrar uma venda porque a API atual não expõe uma listagem administrativa de usuários. A carteira seller sempre usa o usuário autenticado do JWT e não aceita `seller_id` arbitrário.

## API

Todas as respostas são JSON. Erros seguem o formato `{ "error": "codigo", "message": "Descrição" }`. Rotas protegidas recebem:

```http
Authorization: Bearer <token>
```

### Autenticação e acesso

| Método | Rota | Acesso | Descrição |
| --- | --- | --- | --- |
| `GET` | `/health` | público | Healthcheck do backend |
| `POST` | `/auth/login` | público | Login com `email` e `password`; retorna `token` |
| `GET` | `/me` | autenticado | Usuário e papel do token |
| `GET` | `/admin/health` | admin | Verificação de ACL administrativa |

### Produtos — admin

| Método | Rota | Body |
| --- | --- | --- |
| `GET` | `/products` | — |
| `POST` | `/products` | `name`, `sku`, `points_per_unit`, opcional `active` |
| `PUT` | `/products/{id}` | qualquer combinação dos campos permitidos |
| `DELETE` | `/products/{id}` | —; desativa o produto |

`points_per_unit` deve ser inteiro positivo e `sku` é único.

### Campanhas — admin

| Método | Rota | Body |
| --- | --- | --- |
| `GET` | `/campaigns` | — |
| `POST` | `/campaigns` | `name`, `budget_total`, `starts_at`, `ends_at`, opcional `status` |

Datas usam `Y-m-d H:i:s`. A listagem inclui `budget_total`, `budget_used` e `budget_remaining`.

### Vendas — admin

`POST /sales` recebe `external_id`, `campaign_id`, `seller_id`, `product_id`, `quantity` e `unit_value`. Pontos são calculados como `quantity * product.points_per_unit`.

`POST /sales/{external_id}/cancel` cancela a venda e estorna os pontos. Repetir o cancelamento é seguro e idempotente.

### Carteira — seller

`GET /me/wallet` retorna `balance` e `entries`. Cada lançamento possui `type` (`credit` ou `debit`), `points`, `description`, `campaign_id`, `sale_id` e `created_at`. O seller só acessa sua própria carteira.

## Requests

O arquivo [requests.http](requests.http) contém exemplos para login, produtos, campanhas, vendas, cancelamento, carteira e validação de ACL. Ele usa o formato compatível com VS Code REST Client e JetBrains HTTP Client. Ajuste IDs retornados pelas respostas quando estiver usando dados que não sejam o seed limpo.

## Testes e validação

Com os serviços em execução, rode:

```bash
docker compose --env-file .env.example --profile test run --rm backend-test
```

O profile `test` usa uma imagem separada com dependências de desenvolvimento, mantendo PHPUnit fora da imagem runtime do backend. A suíte cobre login correto/incorreto, JWT inválido, ACL, produtos, campanhas, scoring, idempotência, budget, cancelamento e ownership da wallet.

Para uma validação limpa:

```bash
docker compose --env-file .env.example down -v
docker compose --env-file .env.example up --build -d
docker compose --env-file .env.example ps
```

Depois valide o fluxo: login admin, produtos, campanha, venda, budget, login seller, wallet, cancelamento, débito, duplicações, tentativa de rota admin pelo seller e venda acima do budget.

## Decisões, trade-offs e limitações

- PHP puro e React foram mantidos sem framework full-stack ou dependências de roteamento adicionais.
- O ledger é a fonte da verdade, mesmo que no futuro seja criado algum cache de saldo.
- A rejeição integral quando falta budget evita crédito parcial e simplifica auditoria e estorno.
- A coleção HTTP usa tokens copiados manualmente para permanecer portátil entre ferramentas.
- Não há paginação, filtros, importação CSV ou auditoria avançada; são extensões naturais para uma próxima versão.
- Não há endpoint administrativo para listar sellers nem endpoint de histórico de vendas; por isso o admin informa `seller_id` e a UI mostra o último resultado de venda.
- O frontend não possui uma suíte dedicada de testes automatizados; a corretude das regras críticas está coberta pelos testes de integração do backend.

## O que faria diferente com mais tempo

Adicionaria uma listagem administrativa de sellers e vendas, testes de componentes frontend, paginação, filtros, observabilidade estruturada e uma configuração de produção com segredo externo, HTTPS e banco gerenciado.
