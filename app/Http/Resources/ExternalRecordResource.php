<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExternalRecord;

/**
 * Linha do catálogo remoto. `linked` marca quem já tem vínculo apontando para
 * ele — a tela esconde esses do dropdown.
 */
final class ExternalRecordResource
{
    /** @return array<string, mixed> */
    public static function make(ExternalRecord $record): array
    {
        return [
            'system_id' => $record->system_id,
            'type' => $record->type,
            'external_code' => $record->external_code,
            'name' => $record->name ?? '',
            'linked' => (bool) $record->linked,
        ];
    }

    /**
     * @param  array<int, ExternalRecord>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $records): array
    {
        return array_values(array_map(self::make(...), $records));
    }
}
