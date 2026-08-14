<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Enums\EventDirection;
use App\Enums\InvoiceStatus;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\IntegrationException;
use App\Integrations\Sap\SapClient;
use App\Integrations\Sap\SapInvoicePayload;
use App\Models\Entity;
use App\Models\Event;
use App\Models\ExchangeRate;
use App\Models\StagedInvoice;
use App\Support\Flow\StepLog;
use App\Support\Money;
use Carbon\Carbon;
use RuntimeException;

/**
 * Fluxo 2 — uma invoice de filial vira lançamento no SAP.
 *
 * A invoice sempre entra no staging antes de qualquer validação: se algo
 * falhar depois, o dado bruto continua lá com o motivo do bloqueio, pronto
 * para reprocessar. Nada se perde.
 */
final readonly class InvoiceProcessor
{
    public function __construct(
        private SapClient $sap,
        private SapInvoicePayload $payload,
    ) {}

    public function process(IncomingInvoice $invoice): StepLog
    {
        $steps = StepLog::make();

        Logger::info(sprintf(
            '[%s] invoice nova: %s (%s, %s %.2f)',
            $invoice->systemId, $invoice->label(), $invoice->contactName, $invoice->currency, $invoice->total,
        ));

        $staged = $this->stage($invoice, InvoiceStatus::Received);
        $steps->insert('invoice_staging', sprintf(
            'invoice %s entra bruta no staging (id %d, status=received)',
            $invoice->label(), $staged->id,
        ));

        // 1. O cliente da invoice precisa ter vínculo até o SAP.
        $entity = Entity::findByExternalCode($invoice->systemId, $invoice->contactCode);

        if ($entity === null) {
            $steps->select('links', "contato {$invoice->systemId}/{$invoice->contactCode} não vinculado a nenhuma entidade");

            return $this->fail($staged, $steps, $invoice, InvoiceStatus::Blocked,
                "cliente {$invoice->contactName} ({$invoice->contactCode}) sem vínculo no SAP");
        }

        $steps->select('links', "contato {$invoice->systemId}/{$invoice->contactCode} -> entidade {$entity->id} ({$entity->name})");

        $cardCode = $entity->codeIn('sap');

        if ($cardCode === null) {
            return $this->fail($staged, $steps, $invoice, InvoiceStatus::Blocked,
                "entidade {$entity->id} ({$entity->name}) sem código SAP");
        }

        $steps->select('links', "entidade {$entity->id} no sap = CardCode {$cardCode}");

        // 2. Taxa de câmbio vigente na data do documento.
        try {
            $rate = ExchangeRate::vigente($invoice->currency, $invoice->documentDateOrToday());
        } catch (RuntimeException $e) {
            return $this->fail($staged, $steps, $invoice, InvoiceStatus::Blocked, $e->getMessage());
        }

        $amountBrl = Money::round($invoice->total * $rate);
        $steps->select('exchange_rates', sprintf(
            'taxa %s vigente em %s = %.6f -> R$ %.2f',
            $invoice->currency, $invoice->documentDateOrToday()->toDateString(), $rate, $amountBrl,
        ));

        // 3. Lança no SAP.
        try {
            $docEntry = $this->sap->postInvoice($this->payload->build($invoice, $cardCode, $rate));
        } catch (IntegrationException $e) {
            return $this->fail($staged, $steps, $invoice, InvoiceStatus::Error, $e->getMessage());
        }

        $steps->api('SAP API', "POST /Invoices -> DocEntry {$docEntry}");

        $staged->markPosted($docEntry, $rate, $amountBrl);
        $steps->update('invoice_staging', sprintf(
            'status=posted, sap_doc_entry=%d, amount_brl=%.2f', $docEntry, $amountBrl,
        ));

        Event::record('sap', EventDirection::Outbound, 'sap_invoice_post', true, [
            'reference' => $invoice->label(),
            'doc_entry' => $docEntry,
            'amount_brl' => $amountBrl,
        ], entityId: $entity->id, invoiceId: $staged->id);
        $steps->insert('events', 'lançamento registrado no log');

        Logger::info(sprintf('[sap] invoice %s lançada: DocEntry %d (R$ %.2f)', $invoice->label(), $docEntry, $amountBrl));

        return $steps;
    }

    /**
     * Primeira carga de uma filial: o que já existia entra como `ignored`,
     * para o middleware não lançar retroativamente meses de invoice.
     */
    public function registerBaseline(IncomingInvoice $invoice): StagedInvoice
    {
        return $this->stage($invoice, InvoiceStatus::Ignored);
    }

    /** Grava (ou reaproveita) a linha bruta no staging — idempotente por sistema+código. */
    private function stage(IncomingInvoice $invoice, InvoiceStatus $status): StagedInvoice
    {
        return StagedInvoice::updateOrCreate($invoice->systemId, $invoice->externalCode, [
            'payload' => $invoice->raw,
            'type' => 'sale',
            'status' => $status,
            'currency' => $invoice->currency,
            'source_amount' => Money::round($invoice->total),
            'document_date' => $invoice->documentDate,
            'received_at' => Carbon::now(),
        ]);
    }

    private function fail(
        StagedInvoice $staged,
        StepLog $steps,
        IncomingInvoice $invoice,
        InvoiceStatus $status,
        string $reason,
    ): StepLog {
        $staged->markFailed($status, $reason);
        $steps->update('invoice_staging', "status={$status->value}: {$reason}");

        Event::record($invoice->systemId, EventDirection::Inbound, "invoice_{$status->value}", false, [
            'reference' => $invoice->label(),
            'reason' => $reason,
        ], invoiceId: $staged->id);
        $steps->insert('events', 'falha registrada no log');

        Logger::warning("[{$invoice->systemId}] invoice {$invoice->label()} {$status->value}: {$reason}");

        return $steps;
    }
}
