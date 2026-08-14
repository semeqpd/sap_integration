<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A resposta que vai para o navegador.
 *
 * Objeto imutável: o controller devolve uma `Response` e só o front controller
 * (`public/index.php`) chama `send()`. Isso é o que permite aos testes
 * inspecionarem status e corpo sem subir servidor nenhum.
 */
final class Response
{
    /** @param  array<string, string>  $headers */
    private function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers,
    ) {}

    /**
     * Resposta JSON.
     *
     * Sem flags no `json_encode`, de propósito: é exatamente o que o
     * `response()->json()` do Laravel produz, então o corpo continua
     * byte a byte igual ao do `PHP_port`.
     *
     * @param  array<mixed>|\JsonSerializable  $data
     */
    public static function json(array|\JsonSerializable $data, int $status = 200): self
    {
        return new self(
            $status,
            (string) json_encode($data),
            ['Content-Type' => 'application/json'],
        );
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, '', ['Location' => $url]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** O corpo já decodificado — atalho para os testes e para o log. */
    public function decoded(): mixed
    {
        return json_decode($this->body, true);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
