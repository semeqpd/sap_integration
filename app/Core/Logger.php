<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Log de linha única em `storage/logs/app.log`.
 *
 * Sem canais, sem formatadores, sem rotação: uma linha por evento, com data e
 * nível. É o suficiente para o que os serviços registram (`Log::info` e
 * `Log::warning` do Laravel viraram isto).
 */
final class Logger
{
    private static ?string $file = null;

    /** Aponta o log para outro arquivo (o cron usa `cron.log`; os testes, um temporário). */
    public static function useFile(string $path): void
    {
        self::$file = $path;
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    private static function write(string $level, string $message): void
    {
        $file = self::$file ??= storage_path('logs/app.log');

        $directory = dirname($file);

        if (! is_dir($directory)) {
            @mkdir($directory, 0777, recursive: true);
        }

        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);

        // Log quebrado (disco cheio, permissão) não pode derrubar o fluxo que
        // estava sendo registrado.
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
