#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Carga inicial do catálogo: processa todo `database/init/<sistema>/config.json`.
 *
 * Duas garantias:
 *   - se ainda não há config.json/CSV na pasta, não faz nada (não quebra a subida);
 *   - só popula o que ainda não existe — nunca sobrescreve dado já gravado.
 *
 * Para importar na mão, com opções (`--dry-run`, `--manifest=`), use
 * `automations/import_catalog.php`.
 *
 *     php database/seeders/seed_catalog.php
 */

use App\Core\Container;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\CatalogManifest;

require_once __DIR__.'/../../app/bootstrap.php';

if (! function_exists('seed_catalog')) {
    function seed_catalog(): void
    {
        $manifestos = CatalogManifest::discover(database_path('init'));

        if ($manifestos === []) {
            echo "  catálogo inicial: nenhum config.json em database/init — pulando.\n";

            return;
        }

        /** @var CatalogImporter $importer */
        $importer = Container::get(CatalogImporter::class);

        foreach ($manifestos as $caminho) {
            try {
                $relatorios = $importer->import(CatalogManifest::load($caminho), dryRun: false, onlyIfEmpty: true);
            } catch (Throwable $e) {
                // Configuração errada não pode derrubar a subida inteira.
                $relativo = str_replace(base_path().DIRECTORY_SEPARATOR, '', $caminho);
                echo "  catálogo inicial ({$relativo}): {$e->getMessage()}\n";

                continue;
            }

            foreach ($relatorios as $relatorio) {
                echo '  '.$relatorio->summary()."\n";
            }
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    seed_catalog();
}
