<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Templates em PHP puro.
 *
 * `View::render('layout', ['x' => 1])` inclui `resources/views/layout.php` com
 * `$x` disponível e devolve o HTML como string.
 *
 * Dentro do template:
 *   - `<?= e($valor) ?>` para interpolar (escapa, como o `{{ }}` do Blade);
 *   - `<?php include __DIR__.'/partials/topbar.php'; ?>` para incluir um pedaço.
 */
final class View
{
    /** @param  array<string, mixed>  $data */
    public static function render(string $name, array $data = []): string
    {
        $file = self::path($name);

        if (! is_file($file)) {
            throw new RuntimeException("view não encontrada: {$file}");
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    private static function path(string $name): string
    {
        return resource_path('views/'.str_replace('.', '/', $name).'.php');
    }
}
