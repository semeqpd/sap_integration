<?php

declare(strict_types=1);

/**
 * As poucas funções globais do projeto.
 *
 * Carregado por `app/bootstrap.php`, antes de qualquer outra coisa: `env()`
 * precisa existir quando os arquivos de `app/Config/` são lidos, e `e()`
 * precisa existir quando um template é renderizado.
 */

use App\Core\Env;

if (! function_exists('base_path')) {
    /** Caminho absoluto a partir da raiz do projeto. */
    function base_path(string $path = ''): string
    {
        return APP_BASE_PATH.($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, '/\\') : '');
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app'.($path !== '' ? '/'.ltrim($path, '/\\') : ''));
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public'.($path !== '' ? '/'.ltrim($path, '/\\') : ''));
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage'.($path !== '' ? '/'.ltrim($path, '/\\') : ''));
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return base_path('database'.($path !== '' ? '/'.ltrim($path, '/\\') : ''));
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources'.($path !== '' ? '/'.ltrim($path, '/\\') : ''));
    }
}

if (! function_exists('env')) {
    /**
     * Variável de ambiente.
     *
     * **Só `app/Config/*.php` chama esta função.** O resto da aplicação lê
     * `Config::get(...)` — assim existe um lugar único onde se descobre de
     * onde vem cada configuração.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (! function_exists('e')) {
    /**
     * Escapa um valor para sair em HTML.
     *
     * Toda interpolação de valor dinâmico nos templates passa por aqui — é o
     * que o `{{ }}` do Blade fazia sozinho e que, em PHP puro, precisa ser
     * escrito. Sem isso, um nome de cliente com `<script>` viraria XSS.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
