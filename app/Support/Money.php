<?php

declare(strict_types=1);

namespace App\Support;

/** Arredondamento monetário — um lugar só, para não divergir entre telas. */
final class Money
{
    public static function round(float $value, int $decimals = 2): float
    {
        return round($value, $decimals);
    }
}
