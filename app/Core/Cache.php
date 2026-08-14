<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache em arquivo — um JSON por chave em `storage/cache/`.
 *
 * É o suficiente para o que a aplicação guarda: a sessão B1SESSION do SAP, o
 * token/tenant do Xero e a dedupe de erro repetido do poll. Não há Redis nem
 * Memcached de propósito: mantém o `docker-compose.yml` com três serviços.
 */
final class Cache
{
    private static ?string $directory = null;

    /** Troca a pasta do cache (os testes usam uma temporária). */
    public static function useDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = self::read($key);

        return $record === null ? $default : $record['value'];
    }

    public static function has(string $key): bool
    {
        return self::read($key) !== null;
    }

    /** Guarda o valor por `$seconds` segundos (0 ou negativo = para sempre). */
    public static function put(string $key, mixed $value, int $seconds = 0): void
    {
        self::write($key, [
            'expires_at' => $seconds > 0 ? time() + $seconds : null,
            'value' => $value,
        ]);
    }

    /** Devolve o que está no cache; se não houver, roda o callback e guarda o resultado. */
    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        $record = self::read($key);

        if ($record !== null) {
            return $record['value'];
        }

        $value = $callback();
        self::put($key, $value, $seconds);

        return $value;
    }

    public static function forget(string $key): void
    {
        @unlink(self::path($key));
    }

    /** Apaga tudo (usado entre testes). */
    public static function flush(): void
    {
        foreach (glob(self::directory().'/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** @return array{expires_at: int|null, value: mixed}|null */
    private static function read(string $key): ?array
    {
        $file = self::path($key);

        if (! is_file($file)) {
            return null;
        }

        $record = json_decode((string) @file_get_contents($file), true);

        if (! is_array($record) || ! array_key_exists('value', $record)) {
            return null;
        }

        if ($record['expires_at'] !== null && $record['expires_at'] <= time()) {
            @unlink($file);

            return null;
        }

        return $record;
    }

    /** @param  array{expires_at: int|null, value: mixed}  $record */
    private static function write(string $key, array $record): void
    {
        $directory = self::directory();

        if (! is_dir($directory)) {
            @mkdir($directory, 0777, recursive: true);
        }

        @file_put_contents(self::path($key), (string) json_encode($record), LOCK_EX);
    }

    private static function path(string $key): string
    {
        // A chave vira hash: ela contém ':' e outros caracteres que não valem
        // como nome de arquivo em todo sistema.
        return self::directory().'/'.sha1($key).'.json';
    }

    private static function directory(): string
    {
        return self::$directory ??= storage_path('cache');
    }
}
