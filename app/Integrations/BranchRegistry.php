<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Integrations\Contracts\BranchClient;

/**
 * Índice das filiais ativas, resolvido no container (AppServiceProvider).
 *
 * Serviços pedem o registry, nunca um cliente concreto — assim o teste troca
 * uma filial inteira por um fake sem mexer no serviço.
 */
final readonly class BranchRegistry
{
    /** @var array<string, BranchClient> */
    private array $clients;

    /** @param  iterable<BranchClient>  $clients */
    public function __construct(iterable $clients)
    {
        $indexed = [];

        foreach ($clients as $client) {
            $indexed[$client->systemId()] = $client;
        }

        $this->clients = $indexed;
    }

    /** @return array<string, BranchClient> */
    public function all(): array
    {
        return $this->clients;
    }

    public function has(string $systemId): bool
    {
        return isset($this->clients[$systemId]);
    }

    public function get(string $systemId): BranchClient
    {
        return $this->clients[$systemId]
            ?? throw new IntegrationException("filial {$systemId} não está registrada");
    }
}
