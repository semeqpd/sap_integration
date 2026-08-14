<?php

declare(strict_types=1);

namespace App\Services\Results;

use App\Models\Entity;
use App\Models\Link;
use App\Support\Flow\StepLog;

/**
 * Resultado de uma ação que mexe numa entidade (webhook, vínculo manual):
 * o estado final + a sequência de passos que a tela exibe.
 */
final readonly class EntityFlowResult
{
    /** @param  array<int, Link>  $links */
    public function __construct(
        public Entity $entity,
        public array $links,
        public StepLog $steps,
    ) {}
}
