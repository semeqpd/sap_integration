<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;

final class EventResource
{
    /** @return array<string, mixed> */
    public static function make(Event $event): array
    {
        return [
            'id' => $event->id,
            'occurred_at' => $event->occurred_at?->format('Y-m-d H:i:s') ?? '',
            'system_id' => $event->system_id ?? '',
            'direction' => $event->direction->value,
            'action' => $event->action,
            'entity_id' => (int) $event->entity_id,
            'invoice_id' => (int) $event->invoice_id,
            'success' => (bool) $event->success,
            'details' => $event->details ?? [],
        ];
    }

    /**
     * @param  array<int, Event>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $events): array
    {
        return array_values(array_map(self::make(...), $events));
    }
}
