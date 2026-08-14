<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

/**
 * Carrega o `.env` e lê variáveis de ambiente.
 *
 * Regra do projeto: **só os arquivos de `app/Config/` chamam `env()`**. O resto
 * da aplicação lê `Config::get(...)`. Isso mantém um lugar único onde se
 * descobre de onde vem cada configuração.
 */
final class Env
{
    private static bool $loaded = false;

    /**
     * Lê o `.env` da raiz do projeto para `$_ENV`.
     *
     * `safeLoad` não reclama se o arquivo não existir (em produção as variáveis
     * podem vir só do ambiente) e, por ser "immutable", **não sobrescreve**
     * variável que já veio do processo — que é o caso do `docker compose`
     * com `env_file:`.
     */
    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        Dotenv::createImmutable($basePath)->safeLoad();

        self::$loaded = true;
    }

    /**
     * Valor de uma variável de ambiente, com as mesmas conversões do `env()`
     * do Laravel: "true"/"false"/"null"/"empty" viram os valores PHP
     * correspondentes.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
