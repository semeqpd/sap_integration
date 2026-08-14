<?php

declare(strict_types=1);

namespace App\Integrations;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;

/**
 * O pouco que os três clients de API compartilham na hora de falar HTTP.
 *
 * Não é um "cliente HTTP" próprio: quem faz a requisição é o Guzzle, direto.
 * Isto aqui só concentra três decisões que precisam ser iguais nos três:
 *
 *  1. `http_errors => false` — um 401 ou 404 volta como resposta, não como
 *     exceção. Os clients precisam olhar o status (o SAP e o Xero refazem o
 *     login quando veem 401), então erro de HTTP não pode virar exceção do
 *     Guzzle.
 *  2. A URL é montada por concatenação (`base_url` + caminho), e não pelo
 *     `base_uri` do Guzzle — que descartaria o prefixo do caminho
 *     (`/b1s/v1`) ao receber um caminho começando com barra.
 *  3. O `handler` opcional, que é como os testes trocam a rede por respostas
 *     de mentira sem nenhum serviço saber.
 */
trait HttpTransport
{
    private ?Client $guzzle = null;

    /**
     * @param  array<string, mixed>  $options  opções fixas do client (timeout, verify, headers)
     */
    private function http(array $options, ?Closure $handler): Client
    {
        if ($handler !== null) {
            $options['handler'] = HandlerStack::create($handler);
        }

        return $this->guzzle ??= new Client($options + ['http_errors' => false]);
    }

    /** `base_url` + caminho, sem perder o prefixo do caminho da base. */
    private function url(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function failed(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 400;
    }

    /**
     * Corpo decodificado. O stream só pode ser lido uma vez, então quem
     * precisa do corpo cru para a mensagem de erro deve lê-lo antes.
     *
     * @return array<mixed>
     */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
