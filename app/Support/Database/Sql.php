<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Core\Database;

/**
 * Os poucos pedaços de DDL que mudam entre MySQL (produção) e SQLite (testes).
 *
 * O schema real é MySQL — é o que está escrito nas migrations, coluna por
 * coluna. Estes fragmentos existem só porque a suíte roda em SQLite em memória
 * e o SQLite não conhece `bigint unsigned auto_increment`, `tinyint(1)` nem
 * `ENGINE=InnoDB`.
 *
 * Nada aqui é "abstração de banco": é uma lista curta e fechada de equivalências.
 */
final class Sql
{
    private static function sqlite(): bool
    {
        return Database::driver() === 'sqlite';
    }

    /** Chave primária auto-incremento. */
    public static function id(): string
    {
        return self::sqlite()
            ? 'integer PRIMARY KEY AUTOINCREMENT'
            : 'bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    /** Coluna que aponta para um `id()` de outra tabela. */
    public static function foreignId(): string
    {
        return self::sqlite() ? 'integer' : 'bigint unsigned';
    }

    public static function boolean(): string
    {
        return self::sqlite() ? 'integer' : 'tinyint(1)';
    }

    public static function json(): string
    {
        return self::sqlite() ? 'text' : 'json';
    }

    public static function unsignedInteger(): string
    {
        return self::sqlite() ? 'integer' : 'int unsigned';
    }

    /** Carimbo de tempo que nasce preenchido (`DEFAULT now()` do original). */
    public static function timestampNow(): string
    {
        return self::sqlite()
            ? 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'
            : 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP';
    }

    public static function timestampNullable(): string
    {
        return self::sqlite() ? 'datetime NULL' : 'timestamp NULL';
    }

    /** Opções de tabela do MySQL (o SQLite não tem equivalente e nem precisa). */
    public static function tableOptions(): string
    {
        return self::sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /** Concatenação de texto: `CONCAT(...)` no MySQL, `||` no SQLite. */
    public static function concat(string $left, string $right): string
    {
        return self::sqlite() ? "{$left} || {$right}" : "CONCAT({$left}, {$right})";
    }

    /**
     * Lista de valores já escapada, para um `IN (...)`.
     *
     * @param  array<int, string>  $values
     */
    public static function quotedList(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => Database::pdo()->quote($value),
            $values,
        ));
    }
}
