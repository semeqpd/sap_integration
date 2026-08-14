<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Enums\SystemType;

/**
 * Sistema conectado ao middleware (SAP, Stream, Quality, Jaz, Xero).
 *
 * @property string $id
 * @property string $name
 * @property SystemType $type
 * @property string|null $currency
 * @property bool $active
 */
final class System extends Model
{
    protected static string $table = 'systems';

    protected static string $primaryKey = 'id';

    // Chave natural em texto: 'sap', 'jaz_ph'. Não é auto-incremento.
    protected static bool $incrementing = false;

    protected static function casts(): array
    {
        return [
            'type' => SystemType::class,
            'active' => 'boolean',
        ];
    }

    public static function find(string $id): ?self
    {
        return self::fetchFirst('SELECT * FROM systems WHERE id = ?', [$id]);
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::fetchAll('SELECT * FROM systems ORDER BY id');
    }

    /** Nome de exibição de um sistema, com fallback no próprio código. */
    public static function labelFor(string $systemId): string
    {
        $name = Database::scalar('SELECT name FROM systems WHERE id = ?', [$systemId]);

        return $name === null ? $systemId : (string) $name;
    }

    /** Cria ou atualiza — o seeder de sistemas roda a cada subida. */
    public static function upsert(string $id, string $name, SystemType $type, ?string $currency): self
    {
        $system = self::find($id) ?? new self(['id' => $id]);

        $system->name = $name;
        $system->type = $type;
        $system->currency = $currency;
        $system->save();

        return $system;
    }
}
