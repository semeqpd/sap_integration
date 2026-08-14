<?php

declare(strict_types=1);

namespace App\Integrations\Dto;

/** Linha de uma invoice de filial, já normalizada. */
final readonly class IncomingLine
{
    public function __construct(
        public string $name,
        public float $quantity,
        public float $unitPrice,
    ) {}
}
