<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Integrations\Contracts\BranchClient;
use App\Integrations\Dto\ExternalContact;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\IntegrationException;

/** Filial de mentira: devolve o que o teste mandar, sem rede. */
final class FakeBranchClient implements BranchClient
{
    /**
     * @param  array<int, IncomingInvoice>  $invoices
     * @param  array<int, ExternalContact>  $contacts
     */
    public function __construct(
        private readonly string $systemId,
        private array $invoices = [],
        private array $contacts = [],
        private ?string $failWith = null,
    ) {}

    public function systemId(): string
    {
        return $this->systemId;
    }

    public function contacts(): array
    {
        $this->guard();

        return $this->contacts;
    }

    public function invoices(): array
    {
        $this->guard();

        return $this->invoices;
    }

    private function guard(): void
    {
        if ($this->failWith !== null) {
            throw new IntegrationException($this->failWith);
        }
    }
}
