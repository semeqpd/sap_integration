<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Response;
use PHPUnit\Framework\Assert;

/**
 * A resposta devolvida pelo Router, com as asserções que os testes usam.
 *
 * É o equivalente ao `TestResponse` do Laravel, reduzido ao que esta suíte
 * de fato precisa.
 */
final readonly class TestResponse
{
    public function __construct(private Response $response) {}

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertStatus(int $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->response->status(),
            "esperava status {$expected}; corpo: ".$this->response->body(),
        );

        return $this;
    }

    /** Trecho literal no corpo (usado no HTML da página). */
    public function assertSee(string $needle): self
    {
        Assert::assertStringContainsString($needle, $this->response->body());

        return $this;
    }

    /** Valor num caminho com ponto: `assertJsonPath('entity.name', 'Ambev')`. */
    public function assertJsonPath(string $path, mixed $expected): self
    {
        Assert::assertSame($expected, $this->json($path), "caminho \"{$path}\" no JSON");

        return $this;
    }

    /** Tamanho de um array do JSON; sem `$path`, o array da raiz. */
    public function assertJsonCount(int $expected, ?string $path = null): self
    {
        $value = $path === null ? $this->json() : $this->json($path);

        Assert::assertIsArray($value, 'esperava um array em '.($path ?? 'raiz'));
        Assert::assertCount($expected, $value);

        return $this;
    }

    /**
     * Presença das chaves na raiz do JSON.
     *
     * @param  array<int, string>  $keys
     */
    public function assertJsonStructure(array $keys): self
    {
        $decoded = $this->json();

        Assert::assertIsArray($decoded);

        foreach ($keys as $key) {
            Assert::assertArrayHasKey($key, $decoded, "faltou a chave \"{$key}\" na resposta");
        }

        return $this;
    }

    /** 422 com o campo indicado em `errors`. */
    public function assertJsonValidationErrors(string $field): self
    {
        $this->assertStatus(422);

        $errors = $this->json('errors');

        Assert::assertIsArray($errors, 'a resposta 422 não trouxe "errors"');
        Assert::assertArrayHasKey($field, $errors, "esperava erro de validação em \"{$field}\"");

        return $this;
    }

    /** JSON decodificado, inteiro ou num caminho com ponto. */
    public function json(?string $path = null): mixed
    {
        $decoded = $this->response->decoded();

        if ($path === null) {
            return $decoded;
        }

        foreach (explode('.', $path) as $segment) {
            if (! is_array($decoded) || ! array_key_exists($segment, $decoded)) {
                return null;
            }

            $decoded = $decoded[$segment];
        }

        return $decoded;
    }

    public function status(): int
    {
        return $this->response->status();
    }

    public function body(): string
    {
        return $this->response->body();
    }
}
