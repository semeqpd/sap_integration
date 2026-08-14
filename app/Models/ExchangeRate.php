<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use DateTimeInterface;
use RuntimeException;

/**
 * Taxa de câmbio fixa com vigência: 1 unidade da moeda = X BRL.
 *
 * @property string $currency
 * @property string $rate
 */
final class ExchangeRate extends Model
{
    protected static string $table = 'exchange_rates';

    protected static function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'effective_from' => 'date',
        ];
    }

    /**
     * Taxa vigente para a moeda na data do documento.
     *
     * @throws RuntimeException quando não há taxa cadastrada
     */
    public static function vigente(string $currency, DateTimeInterface $date): float
    {
        $rate = Database::scalar(
            'SELECT rate FROM exchange_rates
              WHERE currency = ? AND effective_from <= ?
              ORDER BY effective_from DESC
              LIMIT 1',
            [$currency, $date->format('Y-m-d')],
        );

        if ($rate === null) {
            throw new RuntimeException("sem taxa cadastrada para {$currency}");
        }

        return (float) $rate;
    }

    /** Cria ou atualiza a taxa daquela moeda/vigência (seed idempotente). */
    public static function upsert(string $currency, float $rate, string $effectiveFrom, string $setBy): self
    {
        $existing = self::fetchFirst(
            'SELECT * FROM exchange_rates WHERE currency = ? AND effective_from = ?',
            [$currency, $effectiveFrom],
        ) ?? new self(['currency' => $currency, 'effective_from' => $effectiveFrom]);

        $existing->rate = $rate;
        $existing->set_by = $setBy;

        return $existing->save();
    }
}
