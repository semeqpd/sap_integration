<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

/**
 * Falha ao conversar com um sistema externo (SAP, Jaz, Xero).
 *
 * Erro de integração não derruba o fluxo: vira passo na tela, evento no log e
 * status na invoice.
 */
class IntegrationException extends RuntimeException {}
