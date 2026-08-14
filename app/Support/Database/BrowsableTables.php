<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Core\Exceptions\NotFoundException;

/**
 * Tabelas que a tela "Banco" pode abrir.
 *
 * Whitelist explícita: o nome da tabela entra numa query, então ele nunca
 * pode vir do usuário sem passar por aqui.
 */
final class BrowsableTables
{
    /** @var array<int, string> */
    private const TABLES = [
        'systems',
        'entities',
        'links',
        'external_records',
        'invoice_staging',
        'exchange_rates',
        'events',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return self::TABLES;
    }

    public static function allows(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    /** @throws NotFoundException */
    public static function assert(string $table): string
    {
        if (! self::allows($table)) {
            throw new NotFoundException("tabela \"{$table}\" não é consultável");
        }

        return $table;
    }
}
