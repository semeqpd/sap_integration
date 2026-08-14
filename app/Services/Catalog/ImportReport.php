<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/** O que aconteceu em uma importação — alimenta a saída do comando. */
final class ImportReport
{
    public int $inserted = 0;

    public int $updated = 0;

    public int $existing = 0;      // já estava lá e não foi tocado

    public int $skippedBlank = 0;  // campo obrigatório vazio

    public int $skippedNoMatch = 0; // lookup obrigatório sem correspondente

    public int $skippedFilter = 0;  // barrado pelo `where`

    /** @var array<int, array{external_code: string, name: string}> */
    public array $samples = [];

    public function __construct(
        public readonly string $systemId,
        public readonly string $type,
        public readonly int $sourceRows,
        public readonly bool $dryRun,
    ) {}

    /** @param  array{external_code: string, name: string}  $row */
    public function sample(array $row): void
    {
        if (count($this->samples) < 10) {
            $this->samples[] = $row;
        }
    }

    public function skipped(): int
    {
        return $this->skippedBlank + $this->skippedNoMatch + $this->skippedFilter;
    }

    public function summary(): string
    {
        return sprintf(
            '%s/%s: %d linha(s) de origem -> %d inserida(s), %d atualizada(s), %d já existia(m), %d ignorada(s)%s',
            $this->systemId, $this->type, $this->sourceRows,
            $this->inserted, $this->updated, $this->existing, $this->skipped(),
            $this->dryRun ? ' [simulação, nada foi gravado]' : '',
        );
    }
}
