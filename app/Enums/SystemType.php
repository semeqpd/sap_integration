<?php

declare(strict_types=1);

namespace App\Enums;

/** Natureza de um sistema conectado — CHECK de `systems.type`. */
enum SystemType: string
{
    case Internal = 'internal';  // SAP, Stream, Quality
    case Branch = 'branch';      // filiais com contabilidade própria (Jaz, Xero)

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
