<?php

declare(strict_types=1);

namespace App\Services\Data;

/**
 * Payload do webhook de cadastro do SAP: o BP (CardCode/CardName) e o país da
 * filial correspondente ("php" = Filipinas, "eua" = EUA).
 */
final readonly class SapCustomerEvent
{
    public function __construct(
        public string $cardCode,
        public string $cardName,
        public ?string $country = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            cardCode: trim((string) ($data['CardCode'] ?? '')),
            cardName: trim((string) ($data['CardName'] ?? '')),
            country: isset($data['Country']) ? (string) $data['Country'] : null,
        );
    }
}
