<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use Carbon\Carbon;

/**
 * Cache do que existe em cada sistema — é o dropdown da tela de vínculo.
 *
 * @property string $system_id
 * @property string $type
 * @property string $external_code
 * @property string|null $name
 * @property array<string, mixed>|null $data
 */
final class ExternalRecord extends Model
{
    protected static string $table = 'external_records';

    protected static function casts(): array
    {
        return [
            'data' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function findOne(string $systemId, string $type, string $code): ?self
    {
        return self::fetchFirst(
            'SELECT * FROM external_records WHERE system_id = ? AND type = ? AND external_code = ?',
            [$systemId, $type, $code],
        );
    }

    /**
     * Grava/atualiza uma linha do catálogo (o ON CONFLICT DO UPDATE do sync).
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function remember(string $systemId, string $type, string $code, ?string $name, ?array $data = null): self
    {
        $record = self::findOne($systemId, $type, $code) ?? new self([
            'system_id' => $systemId,
            'type' => $type,
            'external_code' => $code,
        ]);

        $now = Carbon::now();

        $record->name = $name;
        $record->data = $data ?? $record->data;
        $record->last_seen_at = $now;

        if ($record->first_seen_at === null) {
            $record->first_seen_at = $now;
        }

        return $record->save();
    }

    /**
     * O catálogo de um sistema para o dropdown.
     *
     * A subconsulta marca quem já tem vínculo apontando para o mesmo código —
     * a tela esconde esses da lista.
     *
     * @return array<int, self>
     */
    public static function catalogFor(string $systemId, string $type): array
    {
        return self::fetchAll(
            'SELECT er.*,
                    (SELECT 1 FROM links l
                      WHERE l.system_id = er.system_id
                        AND l.entity_type = er.type
                        AND l.external_code = er.external_code
                      LIMIT 1) AS linked
               FROM external_records er
              WHERE er.system_id = ? AND er.type = ?
              ORDER BY er.name',
            [$systemId, $type],
        );
    }

    public static function countFor(string $systemId, string $type): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM external_records WHERE system_id = ? AND type = ?',
            [$systemId, $type],
        );
    }
}
