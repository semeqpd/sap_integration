<?php

declare(strict_types=1);

namespace App\Services\Results;

use App\Support\Flow\StepLog;

/** Resultado da invoice de demonstração injetada pela tela. */
final readonly class DemoInvoiceResult
{
    public function __construct(
        public string $reference,
        public StepLog $steps,
    ) {}
}
