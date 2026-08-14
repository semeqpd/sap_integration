<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Link;

/** Linha da fila de pendências: uma decisão esperando uma pessoa. */
final class PendingLinkResource
{
    /** @return array<string, mixed> */
    public static function make(Link $link): array
    {
        return [
            'link_id' => $link->id,
            'entity_id' => $link->entity_id,
            'entity_name' => $link->entity()?->name,
            'entity_type' => $link->entity_type,
            'system_id' => $link->system_id,
            'system_name' => $link->system()?->name ?? $link->system_id,
            'status' => $link->status->value,
            'created_at' => $link->created_at?->format('Y-m-d H:i:s') ?? '',
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
