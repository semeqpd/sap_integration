<?php

declare(strict_types=1);

namespace App\Services\Results;

use App\Support\Flow\StepLog;

/** Resultado de um ciclo de poll: quantas invoices novas e o que aconteceu. */
final readonly class PollResult
{
    public function __construct(
        public int $new,
        public StepLog $steps,
    ) {}
}
