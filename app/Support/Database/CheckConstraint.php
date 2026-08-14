<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Core\Database;

/**
 * CHECK constraints nas migrations.
 *
 * O schema original (Postgres) usa CHECK em quase toda coluna de status. Aqui
 * eles saem em SQL cru e só no MySQL 8.0.16+, que é onde CHECK é de fato
 * aplicado. Nos testes (SQLite) o enum da aplicação já barra valor inválido
 * antes do banco.
 *
 * A lista de valores vem sempre **do próprio enum** (`LinkStatus::values()`),
 * então a constraint nunca fica dessincronizada do código.
 *
 * Diferente do `PHP_port`, estes métodos **devolvem** o SQL em vez de executá-lo:
 * cada migration é um array de instruções que o `database/migrate.php` aplica
 * dentro de uma transação.
 */
final class CheckConstraint
{
    public static function supported(): bool
    {
        return Database::driver() === 'mysql';
    }

    /** @return array<int, string> vazio quando o banco não suporta CHECK */
    public static function add(string $table, string $name, string $expression): array
    {
        if (! self::supported()) {
            return [];
        }

        return ["ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})"];
    }

    /**
     * Restringe uma coluna a um conjunto de valores (o `CHECK (x IN (...))`).
     *
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    public static function in(string $table, string $column, array $values, bool $nullable = false): array
    {
        if (! self::supported()) {
            return [];
        }

        $list = implode(', ', array_map(
            static fn (string $value): string => Database::pdo()->quote($value),
            $values,
        ));

        $expression = "`{$column}` IN ({$list})";

        if ($nullable) {
            $expression = "`{$column}` IS NULL OR {$expression}";
        }

        return self::add($table, "chk_{$table}_{$column}", $expression);
    }
}
