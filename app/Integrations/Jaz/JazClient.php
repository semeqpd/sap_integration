<?php

declare(strict_types=1);

namespace App\Integrations\Jaz;

use App\Integrations\Contracts\BranchClient;
use App\Integrations\Dto\ExternalContact;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Integrations\HttpTransport;
use App\Integrations\IntegrationException;
use Closure;
use GuzzleHttp\Client;

/** Filial Filipinas — API do Jaz. */
final class JazClient implements BranchClient
{
    use HttpTransport;

    public const SYSTEM_ID = 'jaz_ph';

    /**
     * @param  array<string, mixed>  $config   Config::get('integrations.jaz')
     * @param  Closure|null  $handler  transporte alternativo (só os testes usam)
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Closure $handler = null,
    ) {}

    public function systemId(): string
    {
        return self::SYSTEM_ID;
    }

    /** @return array<int, ExternalContact> */
    public function contacts(): array
    {
        $rows = $this->get('/contacts', (int) $this->config['contacts_page_size']);

        return array_values(array_map(
            static fn (array $row): ExternalContact => new ExternalContact(
                externalCode: (string) ($row['resourceId'] ?? ''),
                name: (string) ($row['name'] ?? ''),
                raw: $row,
            ),
            array_filter($rows, static fn (array $row): bool => ! empty($row['resourceId'])),
        ));
    }

    /** @return array<int, IncomingInvoice> */
    public function invoices(): array
    {
        $rows = $this->get('/invoices', (int) $this->config['page_size']);

        return array_values(array_map($this->toIncomingInvoice(...), $rows));
    }

    /** @param  array<string, mixed>  $row */
    private function toIncomingInvoice(array $row): IncomingInvoice
    {
        $lines = array_map(
            static fn (array $line): IncomingLine => new IncomingLine(
                name: (string) ($line['name'] ?? ''),
                quantity: (float) ($line['quantity'] ?? 0),
                unitPrice: (float) ($line['unitPrice'] ?? 0),
            ),
            $row['lineItems'] ?? [],
        );

        return new IncomingInvoice(
            systemId: self::SYSTEM_ID,
            externalCode: (string) ($row['resourceId'] ?? ''),
            reference: (string) ($row['reference'] ?? ''),
            contactCode: (string) ($row['contact']['resourceId'] ?? $row['contactResourceId'] ?? ''),
            contactName: (string) ($row['contact']['name'] ?? ''),
            currency: (string) ($row['currencyCode'] ?? ''),
            total: (float) ($row['totalAmount'] ?? 0),
            documentDate: IncomingInvoice::dateOnly($row['valueDate'] ?? null),
            dueDate: IncomingInvoice::dateOnly($row['dueDate'] ?? null),
            notes: (string) ($row['invoiceNotes'] ?? ''),
            lines: array_values($lines),
            raw: $row,
        );
    }

    /**
     * GET paginado; devolve o array `data` da resposta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, int $limit, int $offset = 0): array
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new IntegrationException('JAZ_API_KEY não configurada');
        }

        $response = $this->client()->get($this->url((string) $this->config['base_url'], $path), [
            'query' => [
                'limit' => $limit,
                'offset' => $offset,
                'view' => 'full',
            ],
        ]);

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("Jaz retornou {$response->getStatusCode()}: {$body}");
        }

        $data = $this->decode($body)['data'] ?? null;

        if (! is_array($data)) {
            throw new IntegrationException('resposta do Jaz sem o campo "data"');
        }

        return $data;
    }

    private function client(): Client
    {
        return $this->http([
            'timeout' => (int) $this->config['timeout'],
            'headers' => [
                'x-jk-api-key' => (string) $this->config['api_key'],
                'Accept' => 'application/json',
            ],
        ], $this->handler);
    }
}
