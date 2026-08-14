<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * A conexão PDO da aplicação e os atalhos de consulta.
 *
 * Uma conexão só por processo. Isso importa muito nos testes: o banco é um
 * SQLite `:memory:`, que só existe enquanto a conexão viver — abrir uma
 * conexão nova por teste apagaria o schema.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** Profundidade de transação — a partir de 1 usamos SAVEPOINT. */
    private static int $transactions = 0;

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    /** Fecha a conexão. A próxima chamada a `pdo()` reconecta (usado pelo `migrate.php --fresh`). */
    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$transactions = 0;
    }

    public static function driver(): string
    {
        return (string) self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    // -----------------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------------

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::run($sql, $bindings)->fetchAll();
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** Primeira coluna da primeira linha — para `COUNT(*)`, `SELECT name ...` e afins. */
    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $value = self::run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** Executa e devolve quantas linhas foram afetadas. */
    public static function execute(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    /** DDL e afins (CREATE TABLE, ALTER, CREATE VIEW): sem parâmetros, sem retorno. */
    public static function statement(string $sql): void
    {
        self::pdo()->exec($sql);
    }

    public static function lastInsertId(): string
    {
        return (string) self::pdo()->lastInsertId();
    }

    /** Colunas de uma tabela, na ordem em que foram declaradas. */
    public static function columnListing(string $table): array
    {
        if (self::driver() === 'sqlite') {
            // O nome da tabela vem sempre da whitelist de BrowsableTables.
            return array_column(self::select("PRAGMA table_info(\"{$table}\")"), 'name');
        }

        return array_column(self::select(
            'SELECT COLUMN_NAME AS name FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ?
              ORDER BY ORDINAL_POSITION',
            [$table],
        ), 'name');
    }

    // -----------------------------------------------------------------------
    // Transações
    // -----------------------------------------------------------------------

    /**
     * Roda o callback dentro de uma transação: ou tudo entra, ou nada entra.
     *
     * Aninhar é seguro — a partir do segundo nível vira SAVEPOINT, que é o que
     * permite os testes envolverem cada caso numa transação enquanto os
     * serviços abrem as suas.
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
        } catch (Throwable $e) {
            self::rollBack();

            throw $e;
        }

        self::commit();

        return $result;
    }

    public static function beginTransaction(): void
    {
        if (self::$transactions === 0) {
            self::pdo()->beginTransaction();
        } else {
            self::pdo()->exec('SAVEPOINT '.self::savepoint(self::$transactions));
        }

        self::$transactions++;
    }

    public static function commit(): void
    {
        self::$transactions--;

        if (self::$transactions === 0) {
            self::pdo()->commit();
        } else {
            self::pdo()->exec('RELEASE SAVEPOINT '.self::savepoint(self::$transactions));
        }
    }

    public static function rollBack(): void
    {
        self::$transactions--;

        if (self::$transactions === 0) {
            self::pdo()->rollBack();
        } else {
            self::pdo()->exec('ROLLBACK TO SAVEPOINT '.self::savepoint(self::$transactions));
        }
    }

    // -----------------------------------------------------------------------
    // Conexão
    // -----------------------------------------------------------------------

    /** @param  array<int|string, mixed>  $bindings */
    private static function run(string $sql, array $bindings): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    private static function savepoint(int $level): string
    {
        return 'sp'.$level;
    }

    private static function connect(): PDO
    {
        $name = (string) Config::get('database.default', 'mysql');
        $connection = Config::get("database.connections.{$name}");

        if (! is_array($connection)) {
            throw new RuntimeException("conexão de banco \"{$name}\" não está em app/Config/database.php");
        }

        $pdo = match ($connection['driver']) {
            'sqlite' => self::connectSqlite($connection),
            'mysql' => self::connectMysql($connection),
            default => throw new RuntimeException("driver de banco não suportado: {$connection['driver']}"),
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Prepared statement de verdade: o MySQL devolve inteiro como inteiro,
        // e não como string.
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $pdo;
    }

    /** @param  array<string, mixed>  $connection */
    private static function connectSqlite(array $connection): PDO
    {
        $database = (string) $connection['database'];
        $pdo = new PDO("sqlite:{$database}");

        if ((bool) ($connection['foreign_key_constraints'] ?? true)) {
            // No SQLite as FKs vêm desligadas por conexão.
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    /**
     * DSN do MySQL com a mesma precedência de hoje: se `DB_URL` estiver
     * preenchida ela ganha e as variáveis avulsas são ignoradas.
     *
     * @param  array<string, mixed>  $connection
     */
    private static function connectMysql(array $connection): PDO
    {
        $connection = self::applyUrl($connection);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $connection['host'],
            $connection['port'],
            $connection['database'],
            $connection['charset'],
        );

        if (($connection['unix_socket'] ?? '') !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $connection['unix_socket'],
                $connection['database'],
                $connection['charset'],
            );
        }

        $options = [];

        if (($connection['ssl_ca'] ?? null) !== null && $connection['ssl_ca'] !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $connection['ssl_ca'];
        }

        return new PDO(
            $dsn,
            (string) $connection['username'],
            (string) $connection['password'],
            $options,
        );
    }

    /**
     * `mysql://usuario:senha@host:3306/base?charset=utf8mb4` sobrepõe as chaves
     * correspondentes — é o mesmo comportamento que o Laravel tinha ao receber
     * `DB_URL`.
     *
     * @param  array<string, mixed>  $connection
     * @return array<string, mixed>
     */
    private static function applyUrl(array $connection): array
    {
        $url = (string) ($connection['url'] ?? '');

        if ($url === '') {
            return $connection;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new RuntimeException('DB_URL não é uma URL válida');
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return array_merge($connection, array_filter([
            'host' => $parts['host'] ?? null,
            'port' => isset($parts['port']) ? (string) $parts['port'] : null,
            'database' => isset($parts['path']) ? ltrim((string) $parts['path'], '/') : null,
            'username' => isset($parts['user']) ? rawurldecode((string) $parts['user']) : null,
            'password' => isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null,
            'charset' => $query['charset'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }
}
