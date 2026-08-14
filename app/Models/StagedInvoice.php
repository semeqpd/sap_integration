<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Enums\InvoiceStatus;
use Carbon\Carbon;

/**
 * Invoice de filial no staging: entra bruta e nunca se perde.
 *
 * @property int $id
 * @property string $system_id
 * @property string $external_code
 * @property array<string, mixed> $payload
 * @property InvoiceStatus $status
 * @property string|null $block_reason
 * @property string|null $currency
 * @property string|null $source_amount
 * @property Carbon|null $document_date
 * @property string|null $exchange_rate_used
 * @property string|null $amount_brl
 * @property int|null $sap_doc_entry
 * @property int $attempts
 */
final class StagedInvoice extends Model
{
    protected static string $table = 'invoice_staging';

    // Carimbos próprios: received_at na entrada, posted_at no lançamento.

    protected static function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => InvoiceStatus::class,
            'document_date' => 'date',
            'source_amount' => 'decimal:2',
            'exchange_rate_used' => 'decimal:6',
            'amount_brl' => 'decimal:2',
            'sap_doc_entry' => 'integer',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public static function find(int $id): ?self
    {
        return self::fetchFirst('SELECT * FROM invoice_staging WHERE id = ?', [$id]);
    }

    public static function findByExternalCode(string $externalCode): ?self
    {
        return self::fetchFirst(
            'SELECT * FROM invoice_staging WHERE external_code = ? ORDER BY id LIMIT 1',
            [$externalCode],
        );
    }

    /** A última que entrou — o topo da tela e o alvo das asserções nos testes. */
    public static function latest(): ?self
    {
        return self::fetchFirst('SELECT * FROM invoice_staging ORDER BY id DESC LIMIT 1');
    }

    /** @return array<int, self> */
    public static function recent(int $limit = 200): array
    {
        return self::fetchAll("SELECT * FROM invoice_staging ORDER BY id DESC LIMIT {$limit}");
    }

    public static function seen(string $systemId, string $externalCode): bool
    {
        return Database::scalar(
            'SELECT 1 FROM invoice_staging WHERE system_id = ? AND external_code = ? LIMIT 1',
            [$systemId, $externalCode],
        ) !== null;
    }

    /** Já existe alguma invoice desta filial? (`false` = primeira carga, vira baseline) */
    public static function hasAnyFor(string $systemId): bool
    {
        return Database::scalar(
            'SELECT 1 FROM invoice_staging WHERE system_id = ? LIMIT 1',
            [$systemId],
        ) !== null;
    }

    /**
     * Grava a linha bruta, ou atualiza a que já existe para o mesmo
     * sistema+código. É o que torna o poll idempotente.
     *
     * @param  array<string, mixed>  $values
     */
    public static function updateOrCreate(string $systemId, string $externalCode, array $values): self
    {
        $invoice = self::fetchFirst(
            'SELECT * FROM invoice_staging WHERE system_id = ? AND external_code = ?',
            [$systemId, $externalCode],
        ) ?? new self(['system_id' => $systemId, 'external_code' => $externalCode]);

        return $invoice->fill($values)->save();
    }

    /** Referência da invoice na filial — mora no payload bruto. */
    public function reference(): ?string
    {
        $reference = $this->payload['reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /** Marca como lançada no SAP. */
    public function markPosted(int $docEntry, float $rate, float $amountBrl): void
    {
        $this->status = InvoiceStatus::Posted;
        $this->sap_doc_entry = $docEntry;
        $this->exchange_rate_used = $rate;
        $this->amount_brl = $amountBrl;
        $this->block_reason = null;
        $this->posted_at = Carbon::now();
        $this->attempts = $this->attempts + 1;
        $this->save();
    }

    /** Marca como bloqueada/com erro, guardando o motivo para a tela. */
    public function markFailed(InvoiceStatus $status, string $reason): void
    {
        $this->status = $status;
        $this->block_reason = $reason;
        $this->attempts = $this->attempts + 1;
        $this->save();
    }
}
