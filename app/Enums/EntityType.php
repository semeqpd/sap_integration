<?php

declare(strict_types=1);

namespace App\Enums;

/** Tipos de registro canônico — CHECK de `entities.type`. */
enum EntityType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Account = 'account';
    case Item = 'item';
    case CostCenter = 'cost_center';
    case Project = 'project';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
