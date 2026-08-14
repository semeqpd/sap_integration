<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Logger;
use App\Core\Model;
use App\Enums\EventDirection;
use Carbon\Carbon;
use Throwable;

/**
 * Log de tudo que o middleware fez ou recebeu.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property string|null $system_id
 * @property EventDirection $direction
 * @property string $action
 * @property bool $success
 * @property array<string, mixed>|null $details
 */
final class Event extends Model
{
    protected static string $table = 'events';

    protected static function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'direction' => EventDirection::class,
            'success' => 'boolean',
            'details' => 'array',
        ];
    }

    /** @return array<int, self> últimos eventos, do mais novo para o mais velho */
    public static function recent(int $limit = 50): array
    {
        return self::fetchAll("SELECT * FROM events ORDER BY id DESC LIMIT {$limit}");
    }

    /**
     * Registra um evento. Nunca lança: log quebrado não pode derrubar o fluxo
     * que estava sendo registrado.
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(
        ?string $systemId,
        EventDirection $direction,
        string $action,
        bool $success,
        array $details = [],
        ?int $entityId = null,
        ?int $invoiceId = null,
    ): void {
        try {
            (new self([
                'occurred_at' => Carbon::now(),
                'system_id' => $systemId ?: null,
                'direction' => $direction,
                'action' => $action,
                'entity_id' => $entityId ?: null,
                'invoice_id' => $invoiceId ?: null,
                'success' => $success,
                'details' => $details ?: null,
            ]))->save();
        } catch (Throwable $e) {
            Logger::error("não consegui registrar o evento \"{$action}\": {$e->getMessage()}");
        }
    }
}
