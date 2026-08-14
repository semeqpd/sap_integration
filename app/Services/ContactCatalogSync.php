<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Enums\EntityType;
use App\Enums\EventDirection;
use App\Integrations\BranchRegistry;
use App\Integrations\Contracts\BranchClient;
use App\Integrations\IntegrationException;
use App\Models\Event;
use App\Models\ExternalRecord;
use App\Models\System;
use App\Support\Flow\StepLog;

/**
 * Sincroniza o catálogo de clientes das filiais para `external_records` —
 * é o que preenche o dropdown da tela de vínculo.
 */
final readonly class ContactCatalogSync
{
    public function __construct(private BranchRegistry $branches) {}

    public function syncAll(): StepLog
    {
        $steps = StepLog::make();

        foreach ($this->branches->all() as $client) {
            $this->syncBranch($client, $steps);
        }

        return $steps;
    }

    private function syncBranch(BranchClient $client, StepLog $steps): void
    {
        $systemId = $client->systemId();
        $label = System::labelFor($systemId);

        try {
            $contacts = $client->contacts();
        } catch (IntegrationException $e) {
            Logger::warning("[{$systemId}] sync de contatos falhou: {$e->getMessage()}");
            Event::record($systemId, EventDirection::Inbound, 'contacts_sync', false, ['error' => $e->getMessage()]);
            $steps->api($label, 'GET contacts falhou: '.$e->getMessage());

            return;
        }

        foreach ($contacts as $contact) {
            ExternalRecord::remember(
                $systemId,
                EntityType::Customer->value,
                $contact->externalCode,
                $contact->name,
                $contact->raw,
            );
        }

        $count = count($contacts);
        $steps->insert('external_records', "{$label}: {$count} contato(s) sincronizado(s)");
        Event::record($systemId, EventDirection::Inbound, 'contacts_sync', true, ['contacts' => $count]);
        Logger::info("[{$systemId}] catálogo sincronizado: {$count} contatos em external_records");
    }
}
