# PHP_apache — Middleware SAP × filiais em PHP puro

Port de [`../PHP_port`](../PHP_port) (Laravel 12) para **PHP vanilla, sem
framework**, organizado em MVC e rodando em **Apache (mod_php)** dentro de
Docker.

Mesmo contrato de API, mesmo schema de banco, mesmas três telas. O que mudou
foi só quem responde:

- **Tela 1 — Vínculos:** simula o webhook de cadastro do SAP e resolve as pendências de de-para.
- **Tela 2 — Invoices:** poll das filiais (Jaz/PH, Xero/US) e lançamento no SAP.
- **Tela 3 — Banco:** contagem e conteúdo bruto das tabelas.

**Por que existe:** quem dá manutenção neste código tem muita estrada com PHP
puro em Apache e pouca com framework. Aqui não há mágica: o roteador, o acesso
a banco e os templates são código que se lê de cabo a rabo em uma tarde.

---

## 1. Subir com Docker

```bash
cd PHP_apache
cp .env.example .env      # e preencha o bloco de banco
docker compose up -d --build
```

O banco é **externo** — este compose não sobe MySQL nenhum. O container `app`
cuida de `composer install` e das permissões de `storage/`; migração e seed
ficam por sua conta (ou automáticas, com `RUN_MIGRATIONS=true`).

| Serviço | Onde | O que é |
|---|---|---|
| `app` | http://localhost:8083 | Apache + mod_php servindo `public/` |
| `cron` | — | roda `automations/poll.php` a cada minuto e `sync_contacts.php` a cada hora |
| `adminer` | http://localhost:8084 | opcional: `docker compose --profile tools up -d` |

Portas configuráveis no `.env` (`APP_PORT`, `ADMINER_PORT`). As padrão evitam
conflito com o `PHP_port` (8081/8082) e com o stack Go da raiz (8080), então dá
para rodar os três ao mesmo tempo.

Depois de subir:

```bash
docker compose exec app php database/migrate.php --seed
docker compose exec app php automations/import_catalog.php
```

## 2. Subir sem Docker

Precisa de PHP 8.3+ com `pdo_mysql`, `mbstring`, `openssl`, `zip`, Composer e um
MySQL 8 acessível.

```bash
composer install
cp .env.example .env         # preencha o bloco de banco
php database/migrate.php --seed
php -S localhost:8000 -t public
```

O servidor embutido do PHP não aplica as regras de rewrite. Para as rotas
funcionarem, use um roteador de uma linha:

```php
// devserver.php
<?php
$path = $_SERVER['DOCUMENT_ROOT'].parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($path)) { return false; }
require $_SERVER['DOCUMENT_ROOT'].'/index.php';
```

```bash
php -S localhost:8000 -t public devserver.php
```

Num Apache tradicional, aponte o `DocumentRoot` para `public/` e garanta
`AllowOverride All` — o `public/.htaccess` já traz as regras.

Sem o container `cron`, o poll é manual (`php automations/poll.php`) ou pelo
cron do sistema / Agendador de Tarefas do Windows. Ver
[`automations/README.md`](automations/README.md).

## 3. Conexão com o MySQL

Duas formas, e a URL tem precedência:

```dotenv
# 1) URL única — banco gerenciado, homologação, outro host
DB_URL=mysql://usuario:senha@host:3306/middleware?charset=utf8mb4

# 2) variáveis avulsas (padrão do compose)
DB_HOST=192.168.0.201     # 127.0.0.1 se estiver fora do Docker
DB_PORT=3306
DB_DATABASE=sapintegracoes
DB_USERNAME=sap
DB_PASSWORD=
```

Quem faz o parse da URL e sobrepõe as chaves é `App\Core\Database` —
mesma precedência que o Laravel aplicava.

## 4. Estrutura

```
public/               DocumentRoot do Apache
├── index.php         front controller: bootstrap + Router::dispatch()
├── .htaccess         rewrite (o vhost tem as mesmas regras)
├── css/  js/         copiados do PHP_port, sem alteração

app/
├── bootstrap.php     lê .env, monta a config e registra os serviços (à mão)
├── helpers.php       base_path(), env(), e()
├── Config/           app.php, database.php, integrations.php
├── Core/             a mini-infraestrutura que substitui o framework — ver §5
├── Enums/            LinkStatus, InvoiceStatus, EntityType, SystemType, ...
├── Models/           System, Entity, Link, ExternalRecord, StagedInvoice, ExchangeRate, Event
├── Integrations/     tudo que fala com o mundo externo
│   ├── Contracts/    BranchClient — o contrato de uma filial
│   ├── Dto/          IncomingInvoice, IncomingLine, ExternalContact
│   ├── Sap/          SapClient + SapInvoicePayload
│   ├── Jaz/          JazClient          (implementa BranchClient)
│   ├── Xero/         XeroClient         (implementa BranchClient)
│   └── BranchRegistry.php
├── Services/         as regras de negócio (sem HTTP, sem template)
│   ├── CustomerRegistrar.php    fluxo 1: webhook de cadastro
│   ├── LinkResolver.php         fecha pendência de vínculo
│   ├── InvoicePoller.php        ciclo de poll das filiais
│   ├── InvoiceProcessor.php     fluxo 2: invoice -> SAP
│   ├── ContactCatalogSync.php   catálogo -> external_records
│   └── DemoInvoiceInjector.php  invoice sintética da tela
├── Support/Flow/     Step + StepLog — o "fluxo no banco" que a tela mostra
└── Http/             Controllers (finos), Requests (validação), Resources (JSON)

routes/               web.php, api.php, webhooks.php — só chamadas ao Router
resources/views/      PHP puro: layout + partials + uma view por tela
automations/          o que roda por cron ou na mão (ver o README de lá)
database/             migrate.php, migrations/, seeders/, init/ (CSVs)
storage/              cache/, locks/ e logs/ — em arquivo, de propósito
tests/                29 testes cobrindo os dois fluxos ponta a ponta
```

**Regra que sustenta tudo:** controller não tem regra de negócio, serviço não
sabe o que é HTTP, e nada lê `env()` fora de `app/Config/`. Trocar o Jaz por
outra API é escrever um `BranchClient` e listá-lo no `app/bootstrap.php`.

## 5. `app/Core` — o que substitui o framework

Cada peça é pequena e sem mágica: **nada de reflection, autowiring ou anotação.**

| Arquivo | O que é |
|---|---|
| `Env` / `Config` | lê o `.env` e os arrays de `app/Config/`; `Config::get('integrations.sap.base_url')` |
| `Container` | registro de singletons montados à mão no `bootstrap.php` |
| `Request` / `Response` | o único lugar que toca em `$_SERVER`/`$_GET`/`php://input` |
| `Router` | `Router::get('/api/tables/{name}', [TableController::class, 'rows'])` |
| `Database` | PDO singleton, `transaction()` (com savepoint no aninhamento), `select`/`execute` |
| `Model` | base enxuta: casts, `save()` (insert/update pela PK). **Não é um ORM** |
| `View` | `include` + output buffering; `e()` escapa como o `{{ }}` do Blade |
| `Validator` | as regras dos dois formulários que a aplicação recebe |
| `Cache` / `Lock` | JSON em `storage/cache/`; trava com TTL em `storage/locks/` |
| `Logger` | uma linha por evento em `storage/logs/app.log` |
| `ExceptionHandler` | validação → 422, não encontrado → 404, resto → 500 |

Consulta ao banco **não** é genérica: cada model expõe métodos nomeados por
caso de uso, com o SQL à vista.

```php
Link::openWithRelations();                       // a fila da tela de pendências
StagedInvoice::seen('jaz_ph', 'inv-1042');       // idempotência do poll
Entity::findByExternalCode('sap', 'C0056');      // "quem é o C0056 do SAP?"
```

Não existe `->where()->orderBy()->get()` aqui, de propósito: quem lê o código vê
a query, não uma cadeia de chamadas.

## 6. De onde veio cada coisa (Laravel → PHP puro)

| No `PHP_port` | Aqui |
|---|---|
| Eloquent + relations | `App\Core\Model` + métodos estáticos explícitos por model |
| Blade (`*.blade.php`) | PHP puro + `App\Core\View`; `{{ $x }}` virou `<?= e($x) ?>` |
| `FormRequest` | classe em `Http/Requests` com `::fromRequest()` |
| `JsonResource` | classe em `Http/Resources` com `::make()` / `::collection()` |
| `AppServiceProvider::register()` | `app/bootstrap.php`, registro manual e ordenado |
| `Http` facade | Guzzle direto nos clients de `Integrations/` |
| `Cache` facade / `Cache::lock()` | `App\Core\Cache` / `App\Core\Lock` (arquivo) |
| `Log` facade | `App\Core\Logger` |
| `DB::transaction()` | `App\Core\Database::transaction()` |
| `Schedule::command(...)` | `docker/cron/crontab` chamando `automations/` |
| Comandos Artisan | scripts em `automations/` |
| Schema Builder | SQL puro + `database/migrate.php` |
| `RefreshDatabase` | `Tests\TestCase`: migra uma vez, transação por teste |

Enums, DTOs e a maior parte de `Services/` e `Support/` vieram **inalterados** —
já eram PHP sem dependência de framework.

### Duas diferenças visíveis, e o motivo

1. **URL dos assets.** O `asset()` do Laravel gerava URL absoluta
   (`http://host/css/base.css?v=...`); aqui sai relativa (`/css/base.css?v=...`).
   Como o `DocumentRoot` do Apache é a própria pasta `public/`, não é preciso
   saber a URL da aplicação — some a dependência de `APP_URL`. O
   cache-busting por `mtime` continua igual.
2. **`exchange_rates.effective_from` em SQLite.** O cast de data do Eloquent
   gravava `2026-01-01 00:00:00` numa coluna `DATE`; aqui grava `2026-01-01`.
   **No MySQL — o banco de verdade — os dois resultam em `2026-01-01`**, porque
   a coluna trunca. A diferença só aparece no SQLite dos testes.

## 7. Comandos

```bash
docker compose exec app php database/migrate.php --seed     # migra e popula
docker compose exec app php database/migrate.php --status   # o que está aplicado
docker compose exec app php database/migrate.php --fresh --seed   # zera e recria
docker compose exec cron php automations/poll.php           # ciclo de poll agora
docker compose exec cron php automations/sync_contacts.php  # catálogo das filiais
docker compose exec cron tail -f storage/logs/cron.log      # ver o cron rodando
docker compose exec app ./vendor/bin/phpunit                # suíte
```

Sem Docker, é o mesmo sem o prefixo. Detalhes de cada script em
[`automations/README.md`](automations/README.md) e do banco em
[`MIGRATIONS.md`](MIGRATIONS.md).

## 8. API

Mesmos caminhos do `PHP_port` e do middleware em Go — a tela não sabe quem
responde.

| Método | Rota | O que faz |
|---|---|---|
| GET | `/` | a página única, com as três telas |
| POST | `/webhook/sap/customer` | cadastro vindo do SAP (fora do `/api`, contrato fixo) |
| GET | `/api/entities` | de-para consolidado |
| GET | `/api/pending` | fila de vínculos pendentes |
| GET | `/api/external-records?system=&type=` | catálogo do dropdown |
| POST | `/api/links/{link}/resolve` | fecha uma pendência |
| GET | `/api/invoices` | conteúdo do `invoice_staging` |
| POST | `/api/poll` | força um ciclo de poll |
| POST | `/api/invoices/demo` | injeta invoice de teste |
| GET | `/api/events` | log do middleware |
| GET | `/api/tables` · `/api/tables/{name}` | tela Banco |
| GET | `/up` | health check (`{"status":"ok"}`), para monitoração |

Erro de validação responde **422** com o mesmo corpo de antes, que é o que o
`public/js/core/api.js` já sabe ler:

```json
{"message": "CardCode é obrigatório.", "errors": {"CardCode": ["CardCode é obrigatório."]}}
```

## 9. Estado da validação

Rodado no PHP 8.4 do host, com SQLite em memória/arquivo:

- ✅ `php -l` em **131 arquivos PHP** — sem erro de sintaxe
- ✅ `composer install` — as 3 libs de runtime + PHPUnit, sem conflito
- ✅ **29 testes, 116 asserções, todos passando**, sem nenhum aviso de depreciação
- ✅ `database/migrate.php` aplica as 8 migrations; `--status`, `--rollback`,
  `--fresh` e `--seed` funcionam
- ✅ **`--fresh --seed` rodado duas vezes seguidas** — o defeito da view
  `pending_queue` que existia no `PHP_port` não voltou
- ✅ os três scripts de `automations/` rodam sem credencial configurada:
  a falha de cada filial vira passo na saída e aviso no log, sem derrubar o ciclo
- ✅ nenhum arquivo fora de `app/Config/` chama `env()`/`getenv()`
- ✅ toda interpolação nas views passa por `e()` — a única saída crua é o
  `path` do ícone SVG declarado no próprio template

Comparação lado a lado com o `PHP_port` rodando (mesmo seed, mesmos dados):

- ✅ **`GET /` byte a byte igual**, exceto espaço em branco e a forma da URL do
  asset (§6)
- ✅ **`/api/entities`, `/api/pending`, `/api/invoices`, `/api/events`,
  `/api/tables`, `/api/external-records` e `/api/tables/{name}`: JSON idêntico**
  (as únicas diferenças eram os carimbos de tempo dos dois seeds e o caso do
  `effective_from` explicado em §6)
- ✅ **`POST /webhook/sap/customer` idêntico** — entidade, os 3 vínculos e os
  5 `steps`, no mesmo formato
- ✅ **`POST /api/links/{id}/resolve` idêntico**
- ✅ **422 idêntico** nos dois formulários; **404 idêntico** em tabela fora da
  whitelist

Ainda **não** validado:

- ⏳ `docker compose up -d --build` e o schema em MySQL 8 — a máquina onde o
  port foi escrito estava sem o Docker no ar. As CHECK constraints e as opções
  `ENGINE=InnoDB` são o único SQL que não passou por um banco de verdade ainda;
  o resto do schema foi exercitado a cada rodada da suíte.
- ⏳ chamadas reais ao SAP/Jaz/Xero — dependem de credenciais no `.env`
  (nos testes são fakes)

## 10. Próximos passos sugeridos

1. Subir com Docker contra o MySQL de desenvolvimento e conferir no Adminer as
   7 CHECK constraints e a view `pending_queue`.
2. Preencher credenciais reais no `.env` (`SAP_*`, `JAZ_API_KEY`, `XERO_*`).
3. Decidir a periodicidade real do poll (hoje: 1 min para demo; produção: diário).
4. Tirar os de-paras fixos do `app/Config/integrations.php` (conta, item,
   imposto, filial) e levar para tabela + tela.
