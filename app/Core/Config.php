<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configuração da aplicação: os arrays de `app/Config/*.php` lidos uma vez no
 * boot e consultados por caminho com ponto.
 *
 *     Config::get('integrations.sap.base_url')
 *     Config::get('database.connections.mysql.host')
 *
 * O nome do arquivo é a primeira parte do caminho: `app/Config/app.php` vira
 * `Config::get('app....')`.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** Lê todo `app/Config/*.php` para a memória. Chamado uma vez, no boot. */
    public static function load(string $directory): void
    {
        foreach (glob(rtrim($directory, '/\\').'/*.php') ?: [] as $file) {
            self::$items[basename($file, '.php')] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Sobrescreve um valor em memória (não toca no arquivo).
     *
     * Existe para os testes fixarem o ambiente em código — ver
     * `tests/bootstrap.php`.
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $target[$segment] = $value;

                return;
            }

            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    /** Igual ao `get`, mas estoura quando a chave não existe. */
    public static function required(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException("configuração obrigatória ausente: {$key}");
        }

        return $value;
    }
}
