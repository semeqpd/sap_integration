<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de uma invoice no staging — mesmos valores do CHECK de
 * `invoice_staging.status`.
 */
enum InvoiceStatus: string
{
    case Received = 'received';   // entrou bruta, ainda não processada
    case Blocked = 'blocked';     // falta vínculo/de-para para lançar
    case Ready = 'ready';         // pronta para lançar
    case Posted = 'posted';       // lançada no SAP
    case Error = 'error';
    case Ignored = 'ignored';     // já existia antes do middleware (baseline)

    /** Invoices que ainda exigem alguma ação. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Posted, self::Ignored], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
