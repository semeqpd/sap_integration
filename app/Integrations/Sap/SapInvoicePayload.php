<?php

declare(strict_types=1);

namespace App\Integrations\Sap;

use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Support\Money;

/**
 * Monta o corpo do POST /Invoices conforme o mapeamento_campos.xlsx.
 *
 * Conta contábil, item, imposto e filial ainda são de-para fixos
 * (config/integrations.php) — quando virarem tabela, só esta classe muda.
 */
final readonly class SapInvoicePayload
{
    /** @param  array<string, mixed>  $config  config('integrations.sap') */
    public function __construct(private array $config) {}

    /**
     * @param  string  $cardCode  código do cliente no SAP
     * @param  float  $rate  taxa da moeda da filial -> BRL
     * @return array<string, mixed>
     */
    public function build(IncomingInvoice $invoice, string $cardCode, float $rate): array
    {
        $lines = array_map(
            fn (IncomingLine $line): array => [
                'AccountCode' => $this->config['account_code'],
                'ItemCode' => $this->config['item_code'],
                'ItemDescription' => $line->name,
                'Quantity' => $line->quantity,
                'UnitPrice' => Money::round($line->unitPrice * $rate), // moeda da filial -> BRL
                'TaxCode' => $this->config['tax_code'],
            ],
            $invoice->lines,
        );

        $payload = [
            'CardCode' => $cardCode,
            'CardName' => $invoice->contactName,
            'NumAtCard' => $invoice->reference, // referência externa p/ rastrear
            'DocDate' => $invoice->documentDate,
            'DocDueDate' => $invoice->dueDate,
            'Comments' => $invoice->notes,
            'DocumentLines' => array_values($lines),
        ];

        if ((int) $this->config['branch_id'] > 0) {
            $payload['BPL_IDAssignedToInvoice'] = (int) $this->config['branch_id'];
        }

        return $payload;
    }
}
