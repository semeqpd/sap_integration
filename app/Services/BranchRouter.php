<?php

declare(strict_types=1);

namespace App\Services;

/**
 * País informado no cadastro do SAP -> sistema da filial correspondente.
 * O mapa vive em config/integrations.php ('branch_for_country').
 */
final readonly class BranchRouter
{
    /** @param  array<string, string>  $map */
    public function __construct(
        private array $map,
        private string $default,
    ) {}

    public function forCountry(?string $country): string
    {
        $key = mb_strtolower(trim((string) $country));

        return $this->map[$key] ?? $this->default;
    }
}
