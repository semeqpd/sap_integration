<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de um vínculo entidade <-> código externo.
 *
 * Mesmos valores do CHECK da coluna `links.status`: o enum é a fonte da verdade
 * da aplicação e o CHECK é a rede de segurança no banco.
 */
enum LinkStatus: string
{
    case Pending = 'pending';                // esperando decisão de uma pessoa
    case Linked = 'linked';                  // de-para fechado
    case Create = 'create';                  // decidido: criar do outro lado
    case NotApplicable = 'not_applicable';   // não existe correspondente
    case Error = 'error';

    /** Pendências que aparecem na fila da tela. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Create, self::Error], true);
    }

    /** @return array<int, string> */
    public static function openValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isOpen()),
        );
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
