<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StagedInvoice;

/**
 * Linha do invoice_staging na tela.
 *
 * Os valores decimais saem como número (o PDO devolveria string) porque a
 * tela formata com toLocaleString.
 */
final class InvoiceResource
{
    /** @return array<string, mixed> */
    public static function make(StagedInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'system_id' => $invoice->system_id,
            'external_code' => $invoice->external_code,
            'reference' => $invoice->reference() ?? '',
            'status' => $invoice->status->value,
            'block_reason' => $invoice->block_reason ?? '',
            'currency' => $invoice->currency ?? '',
            'source_amount' => (float) $invoice->source_amount,
            'document_date' => $invoice->document_date?->format('Y-m-d') ?? '',
            'exchange_rate_used' => (float) $invoice->exchange_rate_used,
            'amount_brl' => (float) $invoice->amount_brl,
            'sap_doc_entry' => (int) $invoice->sap_doc_entry,
            'attempts' => (int) $invoice->attempts,
            'received_at' => $invoice->received_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * @param  array<int, StagedInvoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $invoices): array
    {
        return array_values(array_map(self::make(...), $invoices));
    }
}
