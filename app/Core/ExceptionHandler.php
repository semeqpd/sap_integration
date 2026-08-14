<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use Throwable;

/**
 * Traduz exceção em resposta HTTP.
 *
 * Fica numa classe (e não solto no `public/index.php`) porque os testes
 * despacham pelo mesmo caminho — assim o 422 que o teste vê é exatamente o
 * 422 que o navegador vê.
 */
final class ExceptionHandler
{
    public static function toResponse(Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            // Mesmo corpo que o Laravel devolvia — é o que public/js/core/api.js lê.
            return Response::json([
                'message' => $e->firstMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof NotFoundException) {
            return Response::json(['message' => $e->getMessage()], 404);
        }

        Logger::error(sprintf('%s: %s (%s:%d)', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

        $debug = (bool) Config::get('app.debug', false);

        return Response::json([
            'message' => $debug ? $e->getMessage() : 'Erro interno.',
            ...($debug ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()] : []),
        ], 500);
    }
}
