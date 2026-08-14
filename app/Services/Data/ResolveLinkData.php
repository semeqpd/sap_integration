<?php

declare(strict_types=1);

namespace App\Services\Data;

/** Decisão tomada na tela para fechar uma pendência de vínculo. */
final readonly class ResolveLinkData
{
    public function __construct(
        public ?string $externalCode,
        public ?string $externalName,
        public string $linkedBy,
        public bool $createNew = false,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $linkedBy = trim((string) ($data['linked_by'] ?? ''));

        return new self(
            externalCode: isset($data['external_code']) ? trim((string) $data['external_code']) : null,
            externalName: isset($data['external_name']) ? trim((string) $data['external_name']) : null,
            linkedBy: $linkedBy !== '' ? $linkedBy : 'tela',
            createNew: (bool) ($data['create_new'] ?? false),
        );
    }
}
