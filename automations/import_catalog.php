#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Carga inicial de catálogo a partir dos CSVs de `database/init/<sistema>/`.
 *
 * O que é importado, e como, está no `config.json` de cada pasta — não aqui.
 *
 *     php automations/import_catalog.php --dry-run        mostra o que faria
 *     php automations/import_catalog.php                  grava
 *     php automations/import_catalog.php --only-if-empty  só se o catálogo estiver vazio
 *     php automations/import_catalog.php --manifest=database/init/stream/config.json
 *
 * Rodado à mão, uma vez. **Não entra no crontab** — o `seed_catalog.php`
 * (chamado no `migrate.php --seed`) já cobre a subida.
 */

use App\Core\Container;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\CatalogManifest;
use App\Services\Catalog\ImportReport;

require __DIR__.'/../app/bootstrap.php';

$flags = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $flags, true);
$onlyIfEmpty = in_array('--only-if-empty', $flags, true);
$manifestFlag = '';

foreach ($flags as $flag) {
    if (str_starts_with($flag, '--manifest=')) {
        $manifestFlag = substr($flag, strlen('--manifest='));
    }
}

$manifestos = $manifestFlag !== ''
    ? [$manifestFlag]
    : CatalogManifest::discover(database_path('init'));

if ($manifestos === []) {
    echo "nenhum config.json encontrado em database/init/*/ — nada a importar.\n";

    exit(0);
}

/** @var CatalogImporter $importer */
$importer = Container::get(CatalogImporter::class);

$falhou = false;

foreach ($manifestos as $caminho) {
    echo "\nmanifesto: ".str_replace(base_path().DIRECTORY_SEPARATOR, '', $caminho)."\n";

    if (! is_file($caminho)) {
        fwrite(STDERR, "  arquivo não encontrado: {$caminho}\n");
        $falhou = true;

        continue;
    }

    try {
        $relatorios = $importer->import(CatalogManifest::load($caminho), $dryRun, $onlyIfEmpty);
    } catch (Throwable $e) {
        fwrite(STDERR, '  '.$e->getMessage()."\n");
        $falhou = true;

        continue;
    }

    foreach ($relatorios as $relatorio) {
        print_report($relatorio);
    }
}

exit($falhou ? 1 : 0);

function print_report(ImportReport $relatorio): void
{
    echo '  '.$relatorio->summary()."\n";

    if ($relatorio->samples === []) {
        return;
    }

    echo "\n  amostra das primeiras linhas:\n";
    printf("    %-32s  %s\n", 'external_code', 'name');

    foreach ($relatorio->samples as $linha) {
        printf("    %-32s  %s\n", $linha['external_code'], $linha['name']);
    }
}
