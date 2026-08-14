<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * Rede de mentira para os clients de integração.
 *
 * Cada client (`SapClient`, `JazClient`, `XeroClient`) aceita um `handler` do
 * Guzzle no construtor. Aqui montamos um que responde a partir de uma função
 * do teste e guarda tudo que foi enviado — é o equivalente ao `Http::fake()`
 * do Laravel, sem lib nenhuma.
 *
 *     $http = new FakeHttp(fn ($request) => FakeHttp::json(['DocEntry' => 4321]));
 *     Container::instance(SapClient::class, new SapClient($config, $http->handler()));
 *     ...
 *     $http->assertNothingSent();
 */
final class FakeHttp
{
    /** @var array<int, RequestInterface> */
    private array $sent = [];

    /** @param  Closure(RequestInterface): Response  $responder */
    public function __construct(private readonly Closure $responder) {}

    /** @param  array<string, mixed>  $data */
    public static function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }

    /** O handler que vai para o construtor do client. */
    public function handler(): Closure
    {
        return function (RequestInterface $request, array $options) {
            $this->sent[] = $request;

            return Create::promiseFor(($this->responder)($request));
        };
    }

    /** @return array<int, RequestInterface> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame(
            [],
            array_map(static fn (RequestInterface $r): string => (string) $r->getUri(), $this->sent),
            'nenhuma requisição HTTP deveria ter sido feita',
        );
    }
}
