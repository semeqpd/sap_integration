<?php

declare(strict_types=1);

namespace App\Integrations\Xero;

use App\Core\Cache;
use App\Integrations\Contracts\BranchClient;
use App\Integrations\Dto\ExternalContact;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Integrations\HttpTransport;
use App\Integrations\IntegrationException;
use Closure;
use GuzzleHttp\Client;

/**
 * Filial EUA — Xero (Custom Connection, OAuth2 client_credentials).
 *
 * O token é machine-to-machine (sem usuário) e vale ~30min; o tenant
 * (organização) é resolvido em /connections. Só invoices ACCREC
 * (contas a receber = vendas) viram invoice no SAP.
 */
final class XeroClient implements BranchClient
{
    use HttpTransport;

    public const SYSTEM_ID = 'xero_us';

    private const TOKEN_KEY = 'xero:access_token';

    private const TENANT_KEY = 'xero:tenant_id';

    /**
     * @param  array<string, mixed>  $config   Config::get('integrations.xero')
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
        $rows = $this->get('/Contacts', 'Contacts');

        return array_values(array_map(
            static fn (array $row): ExternalContact => new ExternalContact(
                externalCode: (string) ($row['ContactID'] ?? ''),
                name: (string) ($row['Name'] ?? ''),
                raw: $row,
            ),
            array_filter($rows, static fn (array $row): bool => ! empty($row['ContactID'])),
        ));
    }

    /** @return array<int, IncomingInvoice> */
    public function invoices(): array
    {
        $rows = $this->get('/Invoices', 'Invoices');

        $sales = array_filter($rows, static fn (array $row): bool => ($row['Type'] ?? '') === 'ACCREC');

        return array_values(array_map($this->toIncomingInvoice(...), $sales));
    }

    /** @param  array<string, mixed>  $row */
    private function toIncomingInvoice(array $row): IncomingInvoice
    {
        $lines = array_map(
            static fn (array $line): IncomingLine => new IncomingLine(
                name: (string) ($line['Description'] ?? ''),
                // O Xero varia entre número e string ("1800.00") no mesmo campo.
                quantity: (float) ($line['Quantity'] ?? 0),
                unitPrice: (float) ($line['UnitAmount'] ?? 0),
            ),
            $row['LineItems'] ?? [],
        );

        $reference = (string) ($row['Reference'] ?? '');

        return new IncomingInvoice(
            systemId: self::SYSTEM_ID,
            externalCode: (string) ($row['InvoiceID'] ?? ''),
            reference: $reference !== '' ? $reference : (string) ($row['InvoiceNumber'] ?? ''),
            contactCode: (string) ($row['Contact']['ContactID'] ?? ''),
            contactName: (string) ($row['Contact']['Name'] ?? ''),
            currency: (string) ($row['CurrencyCode'] ?? ''),
            total: (float) ($row['Total'] ?? 0),
            documentDate: IncomingInvoice::dateOnly($row['DateString'] ?? null),
            dueDate: IncomingInvoice::dateOnly($row['DueDateString'] ?? null),
            notes: '',
            lines: array_values($lines),
            raw: $row,
        );
    }

    /**
     * GET autenticado no serviço de contabilidade.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, string $key, int $page = 1): array
    {
        $response = $this->authenticated($path, $page);

        // Token expirado antes do TTL: renova e repete uma vez.
        if ($response->getStatusCode() === 401) {
            $this->forgetAuth();
            $response = $this->authenticated($path, $page);
        }

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("Xero retornou {$response->getStatusCode()} em {$path}: {$body}");
        }

        $data = $this->decode($body)[$key] ?? null;

        return is_array($data) ? $data : [];
    }

    private function authenticated(string $path, int $page): \Psr\Http\Message\ResponseInterface
    {
        return $this->client()->get($this->url((string) $this->config['base_url'], $path), [
            'query' => ['page' => $page],
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken(),
                'Xero-Tenant-Id' => $this->tenantId(),
            ],
        ]);
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            throw new IntegrationException('XERO_CLIENT_ID/XERO_CLIENT_SECRET não configurados');
        }

        $response = $this->client()->post((string) $this->config['token_url'], [
            'auth' => [$clientId, $clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("autenticação no Xero falhou: {$body}");
        }

        $payload = $this->decode($body);
        $token = (string) ($payload['access_token'] ?? '');

        if ($token === '') {
            throw new IntegrationException("Xero não devolveu access_token: {$body}");
        }

        // Um minuto de folga para não usar token na iminência de expirar.
        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 1800));
        Cache::put(self::TOKEN_KEY, $token, $expiresIn - 60);

        return $token;
    }

    /** Organização conectada à Custom Connection. */
    private function tenantId(): string
    {
        $cached = Cache::get(self::TENANT_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->client()->get((string) $this->config['connections_url'], [
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken(),
                'Accept' => 'application/json',
            ],
        ]);

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("Xero /connections retornou {$response->getStatusCode()}: {$body}");
        }

        $tenantId = (string) ($this->decode($body)[0]['tenantId'] ?? '');

        if ($tenantId === '') {
            throw new IntegrationException(
                'nenhuma organização conectada à Custom Connection — conecte o app a uma org em developer.xero.com'
            );
        }

        Cache::put(self::TENANT_KEY, $tenantId, 12 * 3600);

        return $tenantId;
    }

    private function forgetAuth(): void
    {
        Cache::forget(self::TOKEN_KEY);
        Cache::forget(self::TENANT_KEY);
    }

    private function client(): Client
    {
        return $this->http([
            'timeout' => (int) $this->config['timeout'],
            'headers' => ['Accept' => 'application/json'],
        ], $this->handler);
    }
}
