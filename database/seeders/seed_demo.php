#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seed de demonstração — no real virá da carga inicial/planilha.
 *
 * Mantém os mesmos dados do `001_init.sql` para a demo do lab em Go e os ports
 * PHP mostrarem exatamente a mesma tela.
 *
 *     php database/seeders/seed_demo.php
 */

use App\Enums\EntityType;
use App\Enums\LinkStatus;
use App\Models\Entity;
use App\Models\ExternalRecord;
use App\Models\Link;

require_once __DIR__.'/../../app/bootstrap.php';

if (! function_exists('seed_demo')) {
    function seed_demo(): void
    {
        // Pacific Trade Co. — cliente demo do Jaz já mapeado no SAP.
        // Sem vínculo no Stream de propósito: o webhook cria lá.
        $pacific = demo_entity('Pacific Trade Co.');
        demo_link($pacific, 'sap', 'C0056', 'Pacific Trade Co.');
        demo_link($pacific, 'jaz_ph', '02c73c1d-a22b-4a22-bdd5-c9ec8476b120', 'Pacific Trade Co.');

        // Coca-Cola Jundiaí — exemplo com os três vínculos fechados.
        $coca = demo_entity('Coca-Cola Jundiaí');
        demo_link($coca, 'sap', '220', 'Coca-Cola Jundiaí');
        demo_link($coca, 'stream', '001', 'coca cola-jundiai');
        demo_link($coca, 'jaz_ph', '1234as34', 'coca cola-jundiai');

        // Catálogo do Stream: a API real não existe; as "plantas" vêm da carga
        // inicial em database/init/stream (ver seed_catalog.php).

        // Catálogo do Xero/EUA — opções demo enquanto o sync real não roda.
        demo_catalog('xero_us', [
            'XUS-1001' => 'West Coast Metals LLC',
            'XUS-1002' => 'Liberty Foods Inc.',
            'XUS-1003' => 'Pacific Northwest Traders',
        ]);
    }

    function demo_entity(string $name): Entity
    {
        return Entity::firstOrCreateByName($name, [
            'type' => EntityType::Customer,
            'created_from' => 'sap',
        ]);
    }

    function demo_link(Entity $entity, string $systemId, string $code, string $externalName): void
    {
        Link::firstOrCreateByCode($systemId, EntityType::Customer->value, $code, [
            'entity_id' => $entity->id,
            'external_name' => $externalName,
            'status' => LinkStatus::Linked,
            'source' => 'seed',
        ]);
    }

    /** @param  array<string, string>  $records  código => nome */
    function demo_catalog(string $systemId, array $records): void
    {
        foreach ($records as $code => $name) {
            ExternalRecord::remember($systemId, EntityType::Customer->value, (string) $code, $name);
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    seed_demo();
    echo "  demo: entidades, vínculos e catálogo de demonstração garantidos.\n";
}
