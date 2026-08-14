<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\NotFoundException;
use RuntimeException;

/**
 * Tabela de rotas e despacho.
 *
 * As rotas são declaradas em `routes/*.php`:
 *
 *     Router::get('/api/tables/{name}', [TableController::class, 'rows']);
 *
 * `{name}` casa qualquer coisa que não seja barra e chega no método depois da
 * `Request`, na ordem em que aparece no caminho.
 */
final class Router
{
    /** @var array<string, array<int, array{regex: string, params: array<int, string>, handler: array{0: class-string, 1: string}}>> */
    private static array $routes = [];

    /** @param  array{0: class-string, 1: string}  $handler */
    public static function get(string $path, array $handler): void
    {
        self::add('GET', $path, $handler);
    }

    /** @param  array{0: class-string, 1: string}  $handler */
    public static function post(string $path, array $handler): void
    {
        self::add('POST', $path, $handler);
    }

    /** @throws NotFoundException quando nenhuma rota casa */
    public static function dispatch(Request $request): Response
    {
        foreach (self::$routes[$request->method] ?? [] as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $arguments = [];
            foreach ($route['params'] as $name) {
                $arguments[] = $matches[$name];
            }

            [$class, $method] = $route['handler'];
            $response = Container::get($class)->{$method}($request, ...$arguments);

            if (! $response instanceof Response) {
                throw new RuntimeException("{$class}::{$method}() precisa devolver um App\\Core\\Response");
            }

            return $response;
        }

        throw new NotFoundException("nenhuma rota para {$request->method} {$request->path}");
    }

    /** @return array<int, array{method: string, path: string, action: string}> */
    public static function all(): array
    {
        $list = [];

        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route) {
                $list[] = [
                    'method' => $method,
                    'path' => $route['path'],
                    'action' => $route['handler'][0].'::'.$route['handler'][1],
                ];
            }
        }

        return $list;
    }

    /** @param  array{0: class-string, 1: string}  $handler */
    private static function add(string $method, string $path, array $handler): void
    {
        $params = [];

        // "/api/links/{link}/resolve" -> "#^/api/links/(?P<link>[^/]+)/resolve$#"
        $regex = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '(?P<'.$matches[1].'>[^/]+)';
            },
            $path,
        );

        self::$routes[$method][] = [
            'path' => $path,
            'regex' => '#^'.$regex.'$#',
            'params' => $params,
            'handler' => $handler,
        ];
    }
}
