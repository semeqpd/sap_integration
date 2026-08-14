<?php

declare(strict_types=1);

namespace App\Support\Flow;

use App\Enums\StepOp;
use JsonSerializable;

/**
 * Uma operação feita durante uma ação — é o que a tela mostra como
 * "fluxo no banco". Passo sem tabela vira uma frase simples; passo com tabela
 * ganha a tag colorida (SELECT/INSERT/UPDATE/API).
 */
final readonly class Step implements JsonSerializable
{
    public function __construct(
        public string $desc,
        public ?string $table = null,
        public ?StepOp $op = null,
    ) {}

    /** @return array{table: string|null, op: string|null, desc: string} */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'op' => $this->op?->value,
            'desc' => $this->desc,
        ];
    }

    /** @return array{table: string|null, op: string|null, desc: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
