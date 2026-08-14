#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sistemas conectados — dado estrutural, não é demonstração.
 *
 * As FKs de `links`, `external_records` e `invoice_staging` apontam para
 * `systems`: sem este seed, nada mais entra no banco.
 *
 *     php database/seeders/seed_systems.php
 */

use App\Enums\SystemType;
use App\Models\System;

require_once __DIR__.'/../../app/bootstrap.php';

if (! function_exists('seed_systems')) {
    /** Idempotente: cria o que falta e atualiza o que mudou. */
    function seed_systems(): int
    {
        $systems = [
            ['id' => 'sap', 'name' => 'SAP Business One', 'type' => SystemType::Internal, 'currency' => 'BRL'],
            ['id' => 'stream', 'name' => 'Stream', 'type' => SystemType::Internal, 'currency' => null],
            ['id' => 'quality', 'name' => 'Quality', 'type' => SystemType::Internal, 'currency' => null],
            ['id' => 'jaz_ph', 'name' => 'Jaz (Filipinas)', 'type' => SystemType::Branch, 'currency' => 'PHP'],
            ['id' => 'xero_us', 'name' => 'Xero (EUA)', 'type' => SystemType::Branch, 'currency' => 'USD'],
        ];

        foreach ($systems as $system) {
            System::upsert($system['id'], $system['name'], $system['type'], $system['currency']);
        }

        return count($systems);
    }
}

// Rodado direto pela linha de comando (e não incluído por outro script)?
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    echo '  systems: '.seed_systems()." sistema(s) garantido(s).\n";
}
