<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use App\Integrations\Dto\ExternalContact;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\IntegrationException;

/**
 * Contrato de uma filial com contabilidade própria (Jaz/PH, Xero/US).
 *
 * O poll e o sync de catálogo falam só com esta interface — acrescentar uma
 * filial nova é escrever uma implementação e registrá-la no AppServiceProvider,
 * sem tocar em serviço, controller ou tela.
 */
interface BranchClient
{
    /** Código do sistema em `systems.id` (ex.: 'jaz_ph'). */
    public function systemId(): string;

    /**
     * Clientes da filial, para o catálogo de vínculo.
     *
     * @return array<int, ExternalContact>
     *
     * @throws IntegrationException
     */
    public function contacts(): array;

    /**
     * Invoices de venda da filial, já normalizadas.
     *
     * @return array<int, IncomingInvoice>
     *
     * @throws IntegrationException
     */
    public function invoices(): array;
}
