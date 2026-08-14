<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base dos controllers — só atalhos de resposta.
 *
 * Controller neste projeto não tem regra de negócio: ele lê a requisição,
 * chama um serviço do container e devolve uma `Response`.
 */
abstract class Controller
{
    /** @param  array<mixed>  $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function html(string $body, int $status = 200): Response
    {
        return Response::html($body, $status);
    }

    /** Atalho para `Container::get()` — o controller pega o serviço que precisa. */
    protected function service(string $class): object
    {
        return Container::get($class);
    }
}
