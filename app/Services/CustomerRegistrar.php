<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Enums\EntityType;
use App\Enums\EventDirection;
use App\Enums\LinkStatus;
use App\Models\Entity;
use App\Models\Event;
use App\Models\System;
use App\Services\Data\SapCustomerEvent;
use App\Services\Results\EntityFlowResult;
use App\Support\Flow\StepLog;

/**
 * Fluxo 1 — cadastro de cliente vindo do SAP.
 *
 * Cria (ou reaproveita) a entidade canônica, fecha o vínculo do SAP e abre as
 * pendências que uma pessoa resolve na tela: Stream e a filial do país.
 */
final readonly class CustomerRegistrar
{
    public function __construct(private BranchRouter $router) {}

    public function handle(SapCustomerEvent $event): EntityFlowResult
    {
        $steps = StepLog::make();
        $branchId = $this->router->forCountry($event->country);
        $branchLabel = System::labelFor($branchId);

        Logger::info("[webhook] cadastro do SAP: {$event->cardName} ({$event->cardCode}) — filial {$branchId}");

        // Transação: ou a entidade nasce com todos os vínculos, ou nada entra.
        $entity = Database::transaction(function () use ($event, $branchId, $branchLabel, $steps): Entity {
            $entity = $this->findOrCreateEntity($event, $steps);

            // Stream: pendência para a pessoa escolher a planta na tela.
            $this->openPendingLink($entity, 'stream', 'Stream', 'escolher a planta na tela', $steps);

            // Filial do país (Filipinas ou EUA): também abre pendência.
            $this->openPendingLink($entity, $branchId, $branchLabel, 'escolher o correspondente na tela', $steps);

            return $entity;
        });

        Event::record('sap', EventDirection::Inbound, 'sap_customer_webhook', true, [
            'card_code' => $event->cardCode,
            'card_name' => $event->cardName,
            'country' => $event->country,
            'steps' => $steps->toArray(),
        ], entityId: $entity->id);

        return new EntityFlowResult($entity, $entity->links(), $steps);
    }

    private function findOrCreateEntity(SapCustomerEvent $event, StepLog $steps): Entity
    {
        $entity = Entity::findByExternalCode('sap', $event->cardCode);

        if ($entity !== null) {
            $steps->note("SAP {$event->cardCode} já existe → entidade {$entity->id} ({$entity->name}), nada a criar");

            return $entity;
        }

        $steps->note("Verifica vínculo: SAP {$event->cardCode} não existe → cliente novo");

        $entity = Entity::create([
            'type' => EntityType::Customer,
            'name' => $event->cardName,
            'created_from' => 'sap',
        ]);
        $steps->note("Cria entidade {$entity->id}: \"{$entity->name}\"");

        $entity->createLink([
            'entity_type' => EntityType::Customer->value,
            'system_id' => 'sap',
            'external_code' => $event->cardCode,
            'external_name' => $event->cardName,
            'status' => LinkStatus::Linked,
            'source' => 'auto',
        ]);
        $steps->note("Vincula no SAP: {$event->cardCode} → entidade {$entity->id} (linked)");

        return $entity;
    }

    /**
     * Abre a pendência de um sistema, a menos que já exista vínculo lá.
     *
     * @param  string  $label  nome do sistema como aparece na tela
     * @param  string  $hint  o que a pessoa precisa fazer para fechar a pendência
     */
    private function openPendingLink(
        Entity $entity,
        string $systemId,
        string $label,
        string $hint,
        StepLog $steps,
    ): void {
        $existing = $entity->linkFor($systemId);

        if ($existing !== null) {
            // Vínculo fechado mostra o código; pendência aberta mostra o status
            // (o código ainda é nulo, e "vinculado ()" não diz nada a ninguém).
            $steps->note($existing->external_code !== null
                ? "{$label} já vinculado ({$existing->external_code}), nada a fazer"
                : "{$label} já tem pendência aberta (status {$existing->status->value}), nada a fazer");

            return;
        }

        $entity->createLink([
            'entity_type' => $entity->type->value,
            'system_id' => $systemId,
            'status' => LinkStatus::Pending,
            'source' => 'auto',
        ]);
        $steps->note("{$label}: pendência aberta → {$hint}");
    }
}
