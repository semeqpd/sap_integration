# `automations/` — o que roda fora de uma requisição HTTP

Tudo que **não** é uma requisição do navegador mora aqui: os scripts que rodam
uma vez (na mão) ou de tempos em tempos (cron). Cada arquivo é um script PHP
autoexecutável — não há "kernel", não há framework, só um `require` do
bootstrap da aplicação.

```bash
php automations/poll.php
```

Nada aqui tem regra de negócio própria: cada script resolve um serviço do
container e imprime o resultado. A regra continua em `app/Services/`, a mesma
que a tela usa.

---

## Os scripts

| Script | O que faz | Cadência | Equivalente no `PHP_port` |
|---|---|---|---|
| `poll.php` | Consulta Jaz e Xero e lança no SAP as invoices novas | **a cada minuto** (cron) | `artisan integration:poll` |
| `sync_contacts.php` | Atualiza `external_records` com os contatos das filiais | **a cada hora** (cron) | `artisan integration:sync-contacts` |
| `import_catalog.php` | Carga inicial do catálogo a partir dos CSVs de `database/init/` | manual, uma vez | `artisan integration:import-catalog` |

### `poll.php`

O mesmo ciclo que o botão "Verificar agora" da tela dispara. Sem argumentos.

Imprime um passo por operação e, no fim, quantas invoices novas entraram:

```
  [API Jaz (Filipinas)] GET invoices -> 3 invoices
  [INSERT invoice_staging] invoice INV-1042 entra bruta no staging (id 7, status=received)
  [SELECT links] contato jaz_ph/02c73c1d… -> entidade 1 (Pacific Trade Co.)
  [API SAP API] POST /Invoices -> DocEntry 4321
  1 invoice(s) nova(s)
```

**Dois ciclos nunca rodam ao mesmo tempo.** Antes de começar, o `InvoicePoller`
pega uma trava em arquivo (`storage/locks/`) que vale entre processos — não
importa se o disparo veio do cron ou da tela. Por isso **não** é preciso
`flock` na linha do crontab.

Na primeira execução para uma filial, tudo que já existe lá entra como
`ignored` (*baseline*): o middleware não lança retroativamente meses de
invoice. A partir daí, só o que for novo é processado.

### `sync_contacts.php`

Puxa os clientes de cada filial para `external_records` — é o que preenche o
dropdown da tela de vínculo. Sem argumentos.

Falha de uma filial não derruba a outra: vira passo na saída e evento no log.

### `import_catalog.php`

Lê os CSVs de `database/init/<sistema>/` seguindo o `config.json` de cada
pasta. **O que é importado, e como, está no `config.json` — não no script.**

```bash
php automations/import_catalog.php --dry-run        # mostra o que faria, sem gravar
php automations/import_catalog.php                  # grava
php automations/import_catalog.php --only-if-empty  # só se o catálogo do sistema estiver vazio
php automations/import_catalog.php --manifest=database/init/stream/config.json
```

Nunca sobrescreve registro que já existe, a menos que o manifesto declare
`"on_conflict": "update"`. Sai com código 1 se algum manifesto falhar.

> Não entra no crontab. A subida do container já cobre a carga inicial:
> `database/migrate.php --seed` chama `database/seeders/seed_catalog.php`, que
> é a mesma importação com `--only-if-empty`.

---

## Rodando dentro do Docker

Os scripts vivem no container `cron` (que tem PHP e o código montado):

```bash
docker compose exec cron php automations/poll.php
docker compose exec cron php automations/sync_contacts.php
docker compose exec cron php automations/import_catalog.php --dry-run
```

O container `app` também serve — os dois montam o mesmo código:

```bash
docker compose exec app php automations/poll.php
```

## Acompanhando o cron

A saída de cada execução agendada vai para `storage/logs/cron.log`:

```bash
docker compose exec cron tail -f storage/logs/cron.log
docker compose logs -f cron          # o que o próprio crond diz
```

O log da aplicação (o que os serviços registram) é outro arquivo:

```bash
tail -f storage/logs/app.log
```

## Mudando a cadência

Edite `docker/cron/crontab` e reinicie o container:

```bash
docker compose restart cron
```

O arquivo de hoje:

```cron
* * * * * root cd /var/www/html && php automations/poll.php          >> storage/logs/cron.log 2>&1
0 * * * * root cd /var/www/html && php automations/sync_contacts.php >> storage/logs/cron.log 2>&1
```

> A cadência de um minuto é **ritmo de demonstração**, herdada do lab em Go.
> Em produção o poll do Jaz será diário — combine antes de mudar.

Duas armadilhas conhecidas do cron, já tratadas aqui:

1. **O crontab precisa terminar com uma linha em branco**, senão a última
   entrada é ignorada em silêncio.
2. **O cron não herda as variáveis de ambiente do container.** Não é problema
   neste projeto: os scripts leem o `.env` da raiz por conta própria
   (`App\Core\Env`), então funcionam igual dentro e fora do cron.

## Rodando fora do Docker

No Linux, aponte o cron do sistema para os mesmos scripts:

```cron
* * * * * cd /caminho/para/PHP_apache && php automations/poll.php >> storage/logs/cron.log 2>&1
```

No Windows, use o Agendador de Tarefas chamando
`php C:\caminho\para\PHP_apache\automations\poll.php`.
