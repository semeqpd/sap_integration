<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Integrations\Jaz\JazClient;
use App\Services\Results\DemoInvoiceResult;
use Carbon\Carbon;

/**
 * Injeta uma invoice sintética pelo mesmo caminho do poll — demonstra o fluxo
 * completo sem depender de uma invoice real na filial.
 *
 * O cliente é o Pacific Trade Co., que já vem vinculado no seed.
 */
final readonly class DemoInvoiceInjector
{
    private const DEMO_CONTACT_CODE = '02c73c1d-a22b-4a22-bdd5-c9ec8476b120';

    private const DEMO_CONTACT_NAME = 'Pacific Trade Co.';

    public function __construct(private InvoiceProcessor $processor) {}

    public function inject(): DemoInvoiceResult
    {
        $invoice = $this->buildInvoice();

        return new DemoInvoiceResult($invoice->reference, $this->processor->process($invoice));
    }

    public function buildInvoice(?Carbon $now = null): IncomingInvoice
    {
        $now ??= Carbon::now();

        $lines = [
            new IncomingLine('Rice (Premium Grade)', 10, 85),
            new IncomingLine('Coconut Oil (Virgin)', 5, 120),
        ];

        $total = array_sum(array_map(
            static fn (IncomingLine $line): float => $line->quantity * $line->unitPrice,
            $lines,
        ));

        $externalCode = 'demo-'.$now->getTimestampMs();
        $reference = 'INV-DEMO-'.$now->format('His');
        $documentDate = $now->toDateString();
        $dueDate = $now->copy()->addMonth()->toDateString();
        $notes = 'Invoice de demonstração (injetada pela tela)';

        // O payload bruto imita o formato do Jaz: a tela e as consultas ao
        // staging leem os mesmos campos de uma invoice real.
        $raw = [
            'resourceId' => $externalCode,
            'reference' => $reference,
            'valueDate' => $documentDate,
            'dueDate' => $dueDate,
            'invoiceNotes' => $notes,
            'currencyCode' => 'PHP',
            'totalAmount' => $total,
            'contact' => [
                'resourceId' => self::DEMO_CONTACT_CODE,
                'name' => self::DEMO_CONTACT_NAME,
            ],
            'lineItems' => array_map(
                static fn (IncomingLine $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unitPrice' => $line->unitPrice,
                ],
                $lines,
            ),
            'demo' => true,
        ];

        return new IncomingInvoice(
            systemId: JazClient::SYSTEM_ID,
            externalCode: $externalCode,
            reference: $reference,
            contactCode: self::DEMO_CONTACT_CODE,
            contactName: self::DEMO_CONTACT_NAME,
            currency: 'PHP',
            total: $total,
            documentDate: $documentDate,
            dueDate: $dueDate,
            notes: $notes,
            lines: $lines,
            raw: $raw,
        );
    }
}
