<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operação de um passo do fluxo exibido na tela ("fluxo no banco").
 * O valor vai cru no JSON e a tela usa em minúsculo como classe CSS
 * (`op-select`, `op-insert`, `op-update`, `op-api`).
 */
enum StepOp: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Api = 'API';
}
