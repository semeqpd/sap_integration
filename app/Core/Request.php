<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A requisição HTTP que chegou: método, caminho, query string, corpo e
 * cabeçalhos.
 *
 * É o único lugar que toca em `$_SERVER`/`$_GET`/`php://input` — controller
 * nenhum lê superglobal.
 */
final class Request
{
    /**
     * @param  array<string, mixed>  $query   parâmetros da query string
     * @param  array<string, mixed>  $body    corpo (JSON ou form-encoded)
     * @param  array<string, string>  $headers  cabeçalhos com a chave em minúsculo
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
    ) {}

    /** Monta a requisição a partir das superglobais (usado pelo front controller). */
    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::normalizePath((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $headers = self::headersFromServer();

        return new self($method, $path, $_GET, self::parseBody($headers), $headers);
    }

    /**
     * Monta uma requisição à mão, sem servidor — é como os testes chamam o
     * `Router::dispatch()`.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public static function create(string $method, string $uri, array $body = [], array $headers = []): self
    {
        $path = self::normalizePath((string) parse_url($uri, PHP_URL_PATH));
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        return new self(strtoupper($method), $path, $query, $body, $normalized);
    }

    /** Valor de um campo, olhando primeiro o corpo e depois a query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> query string + corpo (o corpo ganha em caso de empate) */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** A tela e o SAP mandam/aceitam JSON; a página em si é HTML. */
    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('accept'), 'json')
            || str_contains((string) $this->header('content-type'), 'json');
    }

    /** "/api/tables/" -> "/api/tables"; "" -> "/" */
    private static function normalizePath(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return array<string, string> */
    private static function headersFromServer(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        // Estes dois o Apache entrega fora do prefixo HTTP_.
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $server => $name) {
            if (isset($_SERVER[$server])) {
                $headers[$name] = (string) $_SERVER[$server];
            }
        }

        return $headers;
    }

    /**
     * Corpo da requisição: JSON quando o Content-Type diz JSON, senão o
     * form-encoded que o PHP já parseou em `$_POST`.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private static function parseBody(array $headers): array
    {
        if (! str_contains($headers['content-type'] ?? '', 'json')) {
            return $_POST;
        }

        $decoded = json_decode((string) file_get_contents('php://input'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
