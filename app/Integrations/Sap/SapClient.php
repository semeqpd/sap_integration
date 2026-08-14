<?php

declare(strict_types=1);

namespace App\Integrations\Sap;

use App\Core\Cache;
use App\Integrations\HttpTransport;
use App\Integrations\IntegrationException;
use Closure;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Service Layer do SAP Business One.
 *
 * Diferença importante em relação ao middleware em Go: lá a sessão vivia na
 * memória do processo. Em PHP cada request é um processo novo, então o
 * B1SESSION fica no cache compartilhado e é renovado sob demanda — inclusive
 * quando o SAP responde 401 (sessão expirada antes do TTL).
 */
final class SapClient
{
    use HttpTransport;

    private const CACHE_KEY = 'sap:b1session';

    /**
     * @param  array<string, mixed>  $config   Config::get('integrations.sap')
     * @param  Closure|null  $handler  transporte alternativo (só os testes usam; nulo = rede de verdade)
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Closure $handler = null,
    ) {}

    /**
     * Lança a invoice e devolve o DocEntry gerado.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws IntegrationException
     */
    public function postInvoice(array $payload): int
    {
        $response = $this->post('/Invoices', $payload);

        // Sessão pode ter caído antes do TTL: derruba o cache e tenta de novo.
        if ($response->getStatusCode() === 401) {
            $this->forgetSession();
            $response = $this->post('/Invoices', $payload);
        }

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("SAP retornou {$response->getStatusCode()}: {$body}");
        }

        $docEntry = (int) ($this->decode($body)['DocEntry'] ?? 0);

        if ($docEntry === 0) {
            throw new IntegrationException("SAP não devolveu DocEntry: {$body}");
        }

        return $docEntry;
    }

    /** Faz login se necessário e devolve o identificador de sessão. */
    public function session(): string
    {
        $ttl = ((int) $this->config['session_ttl_minutes']) * 60;

        return (string) Cache::remember(self::CACHE_KEY, $ttl, fn (): string => $this->login());
    }

    public function forgetSession(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @param  array<string, mixed>  $payload */
    private function post(string $path, array $payload): ResponseInterface
    {
        return $this->client()->post($this->url((string) $this->config['base_url'], $path), [
            'headers' => ['Cookie' => 'B1SESSION='.$this->session()],
            'json' => $payload,
        ]);
    }

    /** @throws IntegrationException */
    private function login(): string
    {
        $response = $this->client()->post($this->url((string) $this->config['base_url'], '/Login'), [
            'json' => [
                'CompanyDB' => $this->config['company_db'],
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
            ],
        ]);

        $body = (string) $response->getBody();

        if ($this->failed($response)) {
            throw new IntegrationException("login no SAP falhou ({$response->getStatusCode()}): {$body}");
        }

        $sessionId = (string) ($this->decode($body)['SessionId'] ?? '');

        if ($sessionId === '') {
            throw new IntegrationException("login no SAP não devolveu sessão: {$body}");
        }

        return $sessionId;
    }

    private function client(): Client
    {
        return $this->http([
            'timeout' => (int) $this->config['timeout'],
            // O Service Layer do lab usa certificado self-signed.
            'verify' => (bool) $this->config['verify_tls'],
            'headers' => ['Accept' => 'application/json'],
        ], $this->handler);
    }
}
