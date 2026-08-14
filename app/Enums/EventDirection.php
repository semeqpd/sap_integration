<?php

declare(strict_types=1);

namespace App\Enums;

/** Sentido de um evento no log — CHECK de `events.direction`. */
enum EventDirection: string
{
    case Inbound = 'inbound';    // chegou de fora (webhook, poll)
    case Outbound = 'outbound';  // o middleware chamou alguém

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
