<?php

declare(strict_types=1);

namespace App\Integrations\Dto;

/** Contato (cliente) de uma filial — alimenta o catálogo external_records. */
final readonly class ExternalContact
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public string $externalCode,
        public string $name,
        public array $raw = [],
    ) {}
}
