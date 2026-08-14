# Migrations — Postgres (Go) → MySQL

Como o schema de `migrations/001_init.sql` (Postgres) foi traduzido para MySQL 8,
o que muda e por quê.

O schema aqui é **o mesmo** do [`../PHP_port`](../PHP_port/MIGRATIONS.md): mesmas
tabelas, colunas, tipos, índices, FKs, CHECK constraints e a view
`pending_queue`. O que mudou foi só **como** ele é aplicado — o Schema Builder
do Laravel deu lugar a SQL puro e a um aplicador próprio.

Os arquivos estão em [`database/migrations/`](database/migrations).

---

## 1. Como rodar

```bash
php database/migrate.php              # aplica o que ainda não foi aplicado
php database/migrate.php --seed       # aplica e roda os seeders
php database/migrate.php --status     # lista o que está aplicado
php database/migrate.php --fresh      # dropa tudo e reaplica do zero
php database/migrate.php --fresh --seed
php database/migrate.php --rollback   # desfaz o último lote aplicado
```

Dentro do Docker:

```bash
docker compose exec app php database/migrate.php --seed
```

Na subida do container isso roda sozinho se `RUN_MIGRATIONS=true` no `.env`.

## 2. Como uma migration é escrita

Cada arquivo devolve um array com as instruções de ida e de volta:

```php
return [
    'up'   => ['CREATE TABLE systems (...)', 'CREATE INDEX ...'],
    'down' => ['DROP TABLE IF EXISTS systems'],
];
```

O `migrate.php` lê os arquivos em ordem alfabética, aplica os pendentes dentro
de uma transação e registra cada um na tabela `migrations`
(`id`, `migration`, `batch`, `applied_at`).

> A transação vale de fato no SQLite, que tem DDL transacional. No MySQL,
> `CREATE TABLE` dá commit implícito — a transação não desfaz DDL lá. Por isso
> o `migrate.php` tenta o rollback com cuidado: um erro de "no active
> transaction" não pode encobrir o erro de verdade.

**Nada de mágica de framework:** para ver o que uma migration faz, leia o SQL.

### Por que existe `App\Support\Database\Sql`

O schema real é MySQL, e é isso que está escrito nas migrations. Mas a suíte de
testes roda em SQLite em memória, e o SQLite não conhece
`bigint unsigned auto_increment`, `tinyint(1)` nem `ENGINE=InnoDB`. `Sql` é uma
lista curta e fechada de equivalências (`Sql::id()`, `Sql::boolean()`,
`Sql::json()`, …) — não é uma camada de abstração de banco, e não deve virar
uma.

## 3. Um arquivo por tabela, na ordem das dependências

| # | Arquivo | Tabela |
|---|---------|--------|
| 000100 | `create_systems_table` | `systems` |
| 000200 | `create_entities_table` | `entities` |
| 000300 | `create_links_table` | `links` |
| 000400 | `create_external_records_table` | `external_records` |
| 000500 | `create_invoice_staging_table` | `invoice_staging` |
| 000600 | `create_exchange_rates_table` | `exchange_rates` |
| 000700 | `create_events_table` | `events` |
| 000800 | `create_pending_queue_view` | view `pending_queue` |

O prefixo numérico garante a ordem: uma FK só existe depois da tabela que ela
aponta. `events` vem por último porque referencia `entities` **e**
`invoice_staging`. O `--fresh` e o `--rollback` percorrem a lista ao contrário,
pelo mesmo motivo.

## 4. Tradução de tipos

| Postgres (001_init.sql) | MySQL | Observação |
|---|---|---|
| `serial PRIMARY KEY` | `bigint unsigned auto_increment` | `Sql::id()` |
| `text` (chave/índice) | `varchar(191)` / `varchar(255)` | MySQL não indexa `TEXT` sem prefixo — **toda coluna indexada virou `varchar`** |
| `text` (livre) | `text` | ex.: `invoice_staging.block_reason` |
| `jsonb` | `json` | JSON nativo do MySQL 8; sem índice GIN (ver §7) |
| `timestamptz` | `timestamp` | app inteiro em UTC (`APP_TIMEZONE=UTC`) |
| `date` | `date` | igual |
| `numeric(15,2)` | `decimal(15,2)` | PDO devolve string — os models usam cast `decimal:2` |
| `boolean` | `tinyint(1)` | igual |
| `DEFAULT now()` | `DEFAULT CURRENT_TIMESTAMP` | igual |

## 5. Os três pontos que exigiram decisão

Estas decisões vieram do `PHP_port` e **não foram reabertas** — só reproduzidas.

### 5.1 Índice único parcial → índice único comum

```sql
-- Postgres
CREATE UNIQUE INDEX uq_links_code ON links (system_id, entity_type, external_code)
    WHERE external_code IS NOT NULL;
```

MySQL **não tem índice parcial**. Mas também não trata `NULL` como valor em
índice único: várias linhas com `external_code IS NULL` convivem sem conflito.
O `UNIQUE` simples reproduz exatamente a regra original — pendências sem código
continuam podendo existir às dezenas, e um código externo continua apontando
para uma única entidade.

**Equivalência: total.** Nada se perde aqui.

### 5.2 Índices parciais de filtro → índices compostos

| Postgres | Aqui |
|---|---|
| `idx_entities_type ON entities (type) WHERE active` | `(active, type)` |
| `idx_links_open ON links (status) WHERE status IN (...)` | `(status)` |
| `idx_invoice_open ON invoice_staging (status) WHERE status NOT IN (...)` | `(status)` |

**Equivalência: funcional, não física.** O índice fica maior (indexa também as
linhas que o Postgres excluía), mas as consultas da tela são as mesmas e o
volume aqui é pequeno.

### 5.3 CHECK constraints → enum na aplicação + CHECK no banco

1. **A fonte da verdade é um enum PHP** (`app/Enums/`): `LinkStatus`,
   `InvoiceStatus`, `EntityType`, `SystemType`, `EventDirection`. Os models
   fazem cast para eles, então valor inválido nem chega ao banco.
2. **O CHECK continua existindo** como rede de segurança, gerado por
   `App\Support\Database\CheckConstraint`, que lê a lista **do próprio enum** —
   a constraint nunca fica dessincronizada do código.

```php
CheckConstraint::in('links', 'status', LinkStatus::values());
// -> ALTER TABLE `links` ADD CONSTRAINT `chk_links_status` CHECK (`status` IN (...))
```

São 7 constraints, todas só em MySQL 8.0.16+. Nos testes, que rodam em SQLite,
`CheckConstraint::supported()` devolve `false`, o helper devolve lista vazia e
o enum segura a validação.

## 6. Diferenças conscientes em relação ao `001_init.sql`

| O que | Original | Aqui | Por quê |
|---|---|---|---|
| Coluna `pending_queue.system` | `system` | `system_name` | `SYSTEM` é palavra reservada no MySQL 8 |
| Seed | dentro do `001_init.sql` | `database/seeders/` | seed é idempotente e separável do schema |
| Controle de versão | "criou a tabela `systems`? então já rodou" | tabela `migrations` | controle lote a lote, com rollback |

E uma diferença em relação ao `PHP_port`, que lá era imposta pelo Eloquent:

| O que | `PHP_port` | Aqui |
|---|---|---|
| `entities.attributes` | acessor `attrs` no model | lida direto, com cast `array` |

O Eloquent guarda os valores da linha numa propriedade chamada `attributes`, e
a coluna de mesmo nome colidia com ela — daí o acessor. Sem Eloquent, a colisão
não existe e a coluna é lida pelo nome dela.

## 7. O que ficou de fora de propósito

- **Índice GIN em `jsonb`.** Ninguém consulta dentro do `payload` hoje. Quando
  precisar: coluna gerada + índice (`ALTER TABLE invoice_staging ADD COLUMN
  ref VARCHAR(64) AS (payload->>'$.reference') STORED, ADD INDEX (ref)`).
- **Particionamento / retenção de `events`.** A tabela cresce para sempre. Vale
  decidir a política antes de produção.

## 8. Convenção para as próximas migrations

Crie o arquivo à mão, com o próximo número livre:

```
database/migrations/000900_add_x_to_y_table.php
```

Regras que o time segue neste projeto:

1. **Nunca editar uma migration já aplicada** — sempre uma nova.
2. Coluna indexada é `varchar`, nunca `text`.
3. Coluna de estado ganha um enum em `app/Enums/` e um `CheckConstraint::in`.
4. `down` sempre implementado.
5. Dado de negócio (systems, taxas) vive em seeder idempotente, não em migration.

## 9. A armadilha do `--fresh` rodado duas vezes

A migration `000800` faz `DROP VIEW IF EXISTS pending_queue` **antes** do
`CREATE VIEW`. Não é redundância com o `down`: no `PHP_port` esse foi um defeito
real — o comando que zerava o banco derrubava tabelas mas não views, a
`pending_queue` sobrevivia e o segundo `migrate:fresh` quebrava com
"already exists".

Se mexer nessa migration, **mantenha o DROP** e confirme rodando:

```bash
php database/migrate.php --fresh --seed
php database/migrate.php --fresh --seed   # tem que passar igual
```
