<?php

declare(strict_types=1);

/**
 * Front controller — toda requisição HTTP entra por aqui.
 *
 * O Apache manda para cá tudo que não for um arquivo real dentro de `public/`
 * (ver `docker/apache/vhost.conf` e `public/.htaccess`).
 */

use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Core\Router;

require __DIR__.'/../app/bootstrap.php';

// A tabela de rotas. Cada arquivo só chama Router::get()/Router::post().
require __DIR__.'/../routes/web.php';
require __DIR__.'/../routes/api.php';
require __DIR__.'/../routes/webhooks.php';

try {
    $response = Router::dispatch(Request::capture());
} catch (Throwable $e) {
    // Validação -> 422, rota/recurso inexistente -> 404, resto -> 500.
    $response = ExceptionHandler::toResponse($e);
}

$response->send();
