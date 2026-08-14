<?php

declare(strict_types=1);

namespace App\Integrations\Dto;

use Carbon\Carbon;

/**
 * Invoice de filial normalizada.
 *
 * Cada cliente de API (Jaz, Xero) traduz o formato dele para cá, e daí em
 * diante o processamento (staging -> câmbio -> SAP) é idêntico,
 * independente da origem.
 */
final readonly class IncomingInvoice
{
    /**
     * @param  array<int, IncomingLine>  $lines
     * @param  array<string, mixed>  $raw  JSON original, vai bruto para o staging
     */
    public function __construct(
        public string $systemId,
        public string $externalCode,
        public string $reference,
        public string $contactCode,
        public string $contactName,
        public string $currency,
        public float $total,
        public ?string $documentDate,  // yyyy-mm-dd
        public ?string $dueDate,       // yyyy-mm-dd
        public string $notes,
        public array $lines,
        public array $raw,
    ) {}

    /** Data do documento para a busca de câmbio; hoje quando a filial não manda. */
    public function documentDateOrToday(): Carbon
    {
        if ($this->documentDate === null || $this->documentDate === '') {
            return Carbon::today();
        }

        return Carbon::hasFormat($this->documentDate, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $this->documentDate)->startOfDay()
            : Carbon::today();
    }

    /** Rótulo da invoice nas mensagens da tela. */
    public function label(): string
    {
        return $this->reference !== '' ? $this->reference : $this->externalCode;
    }

    /** Corta "2026-05-05T00:00:00Z" para "2026-05-05" (formato aceito pelo SAP). */
    public static function dateOnly(?string $iso): ?string
    {
        if ($iso === null || strlen($iso) < 10) {
            return null;
        }

        return substr($iso, 0, 10);
    }
}
