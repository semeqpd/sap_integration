<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Enums\EventDirection;
use App\Models\Entity;
use App\Models\Event;
use App\Models\ExternalRecord;
use App\Models\Link;
use App\Services\Data\ResolveLinkData;
use App\Services\Results\EntityFlowResult;
use App\Support\Flow\StepLog;

/**
 * Fecha uma pendência de vínculo: a pessoa escolheu qual registro remoto bate
 * com a entidade.
 */
final readonly class LinkResolver
{
    public function handle(Link $link, ResolveLinkData $data): EntityFlowResult
    {
        $steps = StepLog::make();
        $steps->note("Abre pendência {$link->id} (entidade {$link->entity_id}, {$link->system_id})");

        $entity = Database::transaction(function () use ($link, $data, $steps): Entity {
            $externalCode = $data->createNew
                ? $this->createExternalRecord($link, $data, $steps)
                : $data->externalCode;

            if ($externalCode === null || $externalCode === '') {
                throw ValidationException::withMessages([
                    'external_code' => 'Selecione um registro do catálogo ou marque "adicionar novo".',
                ]);
            }

            $link->resolveTo($externalCode, $data->externalName, $data->linkedBy);
            $steps->note("Vincula {$link->system_id} → {$externalCode} (pending → linked, por {$data->linkedBy})");

            return $link->entity();
        });

        Event::record($link->system_id, EventDirection::Outbound, 'link_resolved', true, [
            'link_id' => $link->id,
            'external_code' => $link->external_code,
            'by' => $data->linkedBy,
        ], entityId: $link->entity_id);

        return new EntityFlowResult($entity, $entity->links(), $steps);
    }

    /**
     * "Adicionar novo": ainda não existe API de escrita na filial, então o
     * registro nasce no catálogo local com um código de demonstração.
     */
    private function createExternalRecord(Link $link, ResolveLinkData $data, StepLog $steps): string
    {
        $name = trim((string) $data->externalName);

        if ($name === '') {
            throw ValidationException::withMessages([
                'external_name' => 'Informe o nome do novo registro.',
            ]);
        }

        $code = sprintf('NEW-%d', time() % 1_000_000);

        ExternalRecord::remember($link->system_id, $link->entity_type, $code, $name);
        $steps->note("Cria registro novo no {$link->system_id}: \"{$name}\" ({$code}) [demo — sem API real]");

        return $code;
    }
}
