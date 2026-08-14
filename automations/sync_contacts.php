#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sincroniza o catálogo de clientes das filiais (Jaz, Xero) para
 * `external_records` — é o que preenche o dropdown da tela de vínculo.
 *
 *     php automations/sync_contacts.php
 *
 * Cadência: de hora em hora, pelo cron (ver docker/cron/crontab).
 */

use App\Core\Container;
use App\Services\ContactCatalogSync;

require __DIR__.'/../app/bootstrap.php';

/** @var ContactCatalogSync $sync */
$sync = Container::get(ContactCatalogSync::class);

foreach ($sync->syncAll()->all() as $step) {
    echo "  {$step->desc}\n";
}
