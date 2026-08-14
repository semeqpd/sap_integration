#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Taxas iniciais — no real, cadastradas pela área financeira.
 *
 *     php database/seeders/seed_exchange_rates.php
 */

use App\Models\ExchangeRate;

require_once __DIR__.'/../../app/bootstrap.php';

if (! function_exists('seed_exchange_rates')) {
    /** Idempotente: a chave é moeda + data de vigência. */
    function seed_exchange_rates(): int
    {
        $rates = [
            ['currency' => 'PHP', 'rate' => 0.095, 'effective_from' => '2026-01-01'],
            ['currency' => 'USD', 'rate' => 5.4, 'effective_from' => '2026-01-01'],
            ['currency' => 'EUR', 'rate' => 6.0, 'effective_from' => '2026-01-01'],
            ['currency' => 'BRL', 'rate' => 1.0, 'effective_from' => '2026-01-01'],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::upsert($rate['currency'], $rate['rate'], $rate['effective_from'], 'seed');
        }

        return count($rates);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    echo '  exchange_rates: '.seed_exchange_rates()." taxa(s) garantida(s).\n";
}
