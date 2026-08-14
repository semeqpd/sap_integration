#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Aplicador de migrations.
 *
 *   php database/migrate.php              aplica o que ainda não foi aplicado
 *   php database/migrate.php --seed       aplica e depois roda os seeders
 *   php database/migrate.php --fresh      dropa tudo e reaplica do zero
 *   php database/migrate.php --fresh --seed
 *   php database/migrate.php --rollback   desfaz o último lote aplicado
 *   php database/migrate.php --status     só lista o que está aplicado
 *
 * Cada arquivo de `database/migrations/` devolve um array:
 *
 *     return ['up' => ['CREATE TABLE ...', ...], 'down' => ['DROP TABLE ...']];
 *
 * A ordem é a alfabética do nome do arquivo — o prefixo numérico garante que
 * uma FK só nasça depois da tabela que ela aponta.
 */

use App\Core\Database;

require __DIR__.'/../app/bootstrap.php';

$flags = array_slice($argv, 1);
$fresh = in_array('--fresh', $flags, true);
$rollback = in_array('--rollback', $flags, true);
$status = in_array('--status', $flags, true);
$seed = in_array('--seed', $flags, true);

/** Nome do arquivo (sem `.php`) => caminho, em ordem alfabética. */
function migrations(): array
{
    $files = glob(__DIR__.'/migrations/*.php') ?: [];
    sort($files);

    $list = [];
    foreach ($files as $file) {
        $list[basename($file, '.php')] = $file;
    }

    return $list;
}

/** @return array{up: array<int, string>, down: array<int, string>} */
function instructions(string $file): array
{
    $migration = require $file;

    if (! is_array($migration) || ! isset($migration['up'], $migration['down'])) {
        throw new RuntimeException(basename($file).' precisa devolver [\'up\' => [...], \'down\' => [...]]');
    }

    return $migration;
}

function ensureMigrationsTable(): void
{
    $auto = Database::driver() === 'sqlite'
        ? 'integer PRIMARY KEY AUTOINCREMENT'
        : 'bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY';

    Database::statement("CREATE TABLE IF NOT EXISTS migrations (
        id {$auto},
        migration varchar(191) NOT NULL,
        batch int NOT NULL,
        applied_at varchar(19) NOT NULL
    )");
}

/** @return array<int, string> */
function applied(): array
{
    return array_column(Database::select('SELECT migration FROM migrations ORDER BY id'), 'migration');
}

function nextBatch(): int
{
    return ((int) Database::scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations')) + 1;
}

/**
 * Roda uma lista de instruções dentro de uma transação.
 *
 * Vale de fato no SQLite, que tem DDL transacional. No MySQL, `CREATE TABLE`
 * dá commit implícito — a transação aqui não desfaz DDL, e por isso o
 * rollback é tentado com cuidado, para o erro original não ser encoberto por
 * um "no active transaction".
 *
 * @param  array<int, string>  $statements
 */
function runAll(array $statements): void
{
    Database::beginTransaction();

    try {
        foreach ($statements as $sql) {
            Database::statement($sql);
        }
    } catch (Throwable $e) {
        try {
            Database::rollBack();
        } catch (Throwable) {
            // MySQL já tinha commitado o DDL; o erro que importa é o de baixo.
        }

        throw $e;
    }

    Database::commit();
}

function migratePending(): void
{
    ensureMigrationsTable();

    $applied = applied();
    $batch = nextBatch();
    $pendentes = 0;

    foreach (migrations() as $name => $file) {
        if (in_array($name, $applied, true)) {
            continue;
        }

        echo "  aplicando  {$name}\n";

        runAll(instructions($file)['up']);

        Database::execute(
            'INSERT INTO migrations (migration, batch, applied_at) VALUES (?, ?, ?)',
            [$name, $batch, date('Y-m-d H:i:s')],
        );

        $pendentes++;
    }

    echo $pendentes === 0
        ? "  nada a aplicar — o banco já está atualizado.\n"
        : "  {$pendentes} migration(s) aplicada(s).\n";
}

function rollbackLastBatch(): void
{
    ensureMigrationsTable();

    $batch = (int) Database::scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations');

    if ($batch === 0) {
        echo "  nada a desfazer.\n";

        return;
    }

    // Do mais novo para o mais antigo: uma tabela só cai depois de quem a referencia.
    $names = array_column(
        Database::select('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC', [$batch]),
        'migration',
    );

    $arquivos = migrations();

    foreach ($names as $name) {
        if (! isset($arquivos[$name])) {
            echo "  AVISO: {$name} está registrada mas o arquivo sumiu — pulando.\n";

            continue;
        }

        echo "  desfazendo {$name}\n";

        runAll(instructions($arquivos[$name])['down']);
        Database::execute('DELETE FROM migrations WHERE migration = ?', [$name]);
    }

    echo '  lote '.$batch." desfeito (".count($names)." migration(s)).\n";
}

/**
 * Dropa tudo, do fim para o começo: a view `pending_queue` cai primeiro (é a
 * 000800), depois `events`, e por último `systems`. É a ordem inversa das
 * dependências.
 */
function dropAll(): void
{
    foreach (array_reverse(migrations(), preserve_keys: true) as $name => $file) {
        echo "  dropando   {$name}\n";
        runAll(instructions($file)['down']);
    }

    Database::statement('DROP TABLE IF EXISTS migrations');
}

function showStatus(): void
{
    ensureMigrationsTable();

    $applied = applied();

    foreach (migrations() as $name => $file) {
        printf("  [%s] %s\n", in_array($name, $applied, true) ? 'x' : ' ', $name);
    }
}

// ---------------------------------------------------------------------------

echo "\n";

try {
    if ($status) {
        showStatus();
    } elseif ($rollback) {
        rollbackLastBatch();
    } else {
        if ($fresh) {
            echo "  zerando o banco (--fresh)\n";
            dropAll();
        }

        migratePending();

        if ($seed) {
            echo "\n";
            require __DIR__.'/seed.php';
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\n  ERRO: {$e->getMessage()}\n\n");

    exit(1);
}

echo "\n";
