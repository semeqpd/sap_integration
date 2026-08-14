<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URL de asset com cache-busting pelo mtime do arquivo: editar um .css/.js no
 * host reflete no navegador sem hard refresh e sem passo de build.
 *
 * O caminho sai relativo à raiz do site (`/css/base.css?v=...`) — como o
 * DocumentRoot do Apache é a própria pasta `public/`, não é preciso saber a
 * URL da aplicação.
 */
final class Assets
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $full = public_path($path);
        $version = is_file($full) ? (string) filemtime($full) : '1';

        return '/'.$path.'?v='.$version;
    }
}
