<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Link;

final class LinkResource
{
    /** @return array<string, mixed> */
    public static function make(Link $link): array
    {
        return [
            'id' => $link->id,
            'entity_id' => $link->entity_id,
            'entity_type' => $link->entity_type,
            'system_id' => $link->system_id,
            'external_code' => $link->external_code ?? '',
            'external_name' => $link->external_name ?? '',
            'status' => $link->status->value,
            'source' => $link->source,
        ];
    }

    /**
     * @param  array<int, Link>  $links
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $links): array
    {
        return array_values(array_map(self::make(...), $links));
    }
}
