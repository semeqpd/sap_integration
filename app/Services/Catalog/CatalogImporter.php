<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\ExternalRecord;
use InvalidArgumentException;

/**
 * Executa o config.manifest: lê os CSVs de carga inicial, cruza os arquivos e
 * grava o catálogo em `external_records`.
 *
 * Regra de ouro: **não sobrescreve dado existente**. Um registro que já está
 * no banco é contado e deixado como está, a menos que a importação declare
 * "on_conflict": "update" no manifesto.
 */
final readonly class CatalogImporter
{
    private const PLACEHOLDER = '/\{([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\}/';

    /**
     * @return array<int, ImportReport>
     */
    public function import(CatalogManifest $manifest, bool $dryRun = false, bool $onlyIfEmpty = false): array
    {
        $relatorios = [];

        foreach ($manifest->enabledImports() as $definicao) {
            $relatorios[] = $this->importOne($manifest->directory, $definicao, $dryRun, $onlyIfEmpty);
        }

        return $relatorios;
    }

    private function importOne(
        string $directory,
        CatalogImportDefinition $def,
        bool $dryRun,
        bool $onlyIfEmpty,
    ): ImportReport {
        $base = CsvFile::read("{$directory}/{$def->baseFile}", $def->delimiter, $def->enclosure);

        /** @var array<string, CsvFile> $arquivos */
        $arquivos = [$def->baseAlias => $base];
        /** @var array<string, array<string, array<string, string>>> $indices */
        $indices = [];

        foreach ($def->lookups as $lookup) {
            $csv = CsvFile::read("{$directory}/{$lookup->file}", $def->delimiter, $def->enclosure);
            $this->assertColumn($csv, $lookup->rightColumn, "{$lookup->alias}.{$lookup->rightColumn} (chave do lookup)");

            $arquivos[$lookup->alias] = $csv;
            $indices[$lookup->alias] = $csv->indexBy($lookup->rightColumn);
        }

        $this->validate($def, $arquivos);

        $relatorio = new ImportReport($def->systemId, $def->type, count($base->rows), $dryRun);

        // "só sobe se ainda não existir": se o catálogo deste sistema já tem
        // conteúdo, a carga inicial não roda de novo.
        if ($onlyIfEmpty && $this->alreadyPopulated($def)) {
            $relatorio->existing = $this->countExisting($def);

            return $relatorio;
        }

        foreach ($base->rows as $linhaBase) {
            $contexto = [$def->baseAlias => $linhaBase];
            $semCorrespondente = false;

            foreach ($def->lookups as $lookup) {
                $chave = $this->value($contexto, $lookup->leftField, $arquivos, permissive: true);
                $encontrado = $indices[$lookup->alias][$chave] ?? null;

                if ($encontrado === null) {
                    if ($lookup->required) {
                        $semCorrespondente = true;
                        break;
                    }
                    // Lookup opcional sem correspondente: colunas vazias.
                    $encontrado = array_fill_keys($arquivos[$lookup->alias]->headers, '');
                }

                $contexto[$lookup->alias] = $encontrado;
            }

            if ($semCorrespondente) {
                $relatorio->skippedNoMatch++;

                continue;
            }

            if (! $this->passesFilter($def, $contexto, $arquivos)) {
                $relatorio->skippedFilter++;

                continue;
            }

            if ($this->hasBlankRequired($def, $contexto, $arquivos)) {
                $relatorio->skippedBlank++;

                continue;
            }

            $externalCode = $this->render($def->externalCodeTemplate, $contexto, $arquivos);
            $name = $this->render($def->nameTemplate, $contexto, $arquivos);

            if ($externalCode === '') {
                $relatorio->skippedBlank++;

                continue;
            }

            $relatorio->sample(['external_code' => $externalCode, 'name' => $name]);

            $existente = ExternalRecord::findOne($def->systemId, $def->type, $externalCode);

            if ($existente !== null && ! $def->overwriteExisting) {
                $relatorio->existing++;

                continue;
            }

            if ($dryRun) {
                $existente === null ? $relatorio->inserted++ : $relatorio->updated++;

                continue;
            }

            ExternalRecord::remember($def->systemId, $def->type, $externalCode, $name, $this->flatten($contexto));

            $existente === null ? $relatorio->inserted++ : $relatorio->updated++;
        }

        return $relatorio;
    }

    /**
     * Confere, antes de importar, que todo {alias.coluna} usado existe mesmo.
     * Erro aqui é de configuração — a mensagem diz o campo e as opções.
     *
     * @param  array<string, CsvFile>  $arquivos
     */
    private function validate(CatalogImportDefinition $def, array $arquivos): void
    {
        $campos = array_merge(
            $this->placeholders($def->externalCodeTemplate),
            $this->placeholders($def->nameTemplate),
            $def->skipWhenBlank,
            array_keys($def->where),
            array_map(static fn (CatalogLookup $l): string => $l->leftField, $def->lookups),
        );

        foreach (array_unique($campos) as $campo) {
            [$alias, $coluna] = $this->split($campo);

            if (! isset($arquivos[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'campo "%s" usa o alias "%s", que não existe nesta importação (aliases: %s).',
                    $campo, $alias, implode(', ', $def->aliases()),
                ));
            }

            $this->assertColumn($arquivos[$alias], $coluna, $campo);
        }
    }

    private function assertColumn(CsvFile $csv, string $column, string $referencia): void
    {
        if (! $csv->hasColumn($column)) {
            throw new InvalidArgumentException(sprintf(
                'a coluna "%s" (usada em "%s") não existe em %s. Colunas disponíveis: %s.',
                $column, $referencia, basename($csv->path), $csv->columnList(),
            ));
        }
    }

    /** @return array<int, string> */
    private function placeholders(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $m, PREG_SET_ORDER);

        return array_map(static fn (array $achado): string => "{$achado[1]}.{$achado[2]}", $m);
    }

    /** @return array{0: string, 1: string} */
    private function split(string $campo): array
    {
        $partes = explode('.', $campo, 2);

        if (count($partes) !== 2) {
            throw new InvalidArgumentException("campo \"{$campo}\" deve ter a forma alias.coluna.");
        }

        return [$partes[0], $partes[1]];
    }

    /**
     * @param  array<string, array<string, string>>  $contexto
     * @param  array<string, CsvFile>  $arquivos
     */
    private function value(array $contexto, string $campo, array $arquivos, bool $permissive = false): string
    {
        [$alias, $coluna] = $this->split($campo);

        if (! isset($contexto[$alias]) && ! $permissive) {
            throw new InvalidArgumentException("campo \"{$campo}\": alias \"{$alias}\" não disponível.");
        }

        return $contexto[$alias][$coluna] ?? '';
    }

    /**
     * @param  array<string, array<string, string>>  $contexto
     * @param  array<string, CsvFile>  $arquivos
     */
    private function render(string $template, array $contexto, array $arquivos): string
    {
        $resultado = preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $m): string => $this->value($contexto, "{$m[1]}.{$m[2]}", $arquivos),
            $template,
        );

        return trim((string) $resultado);
    }

    /**
     * @param  array<string, array<string, string>>  $contexto
     * @param  array<string, CsvFile>  $arquivos
     */
    private function hasBlankRequired(CatalogImportDefinition $def, array $contexto, array $arquivos): bool
    {
        foreach ($def->skipWhenBlank as $campo) {
            if ($this->value($contexto, $campo, $arquivos) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, string>>  $contexto
     * @param  array<string, CsvFile>  $arquivos
     */
    private function passesFilter(CatalogImportDefinition $def, array $contexto, array $arquivos): bool
    {
        foreach ($def->where as $campo => $aceitos) {
            if (! in_array($this->value($contexto, $campo, $arquivos), $aceitos, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guarda a linha de origem inteira em external_records.data — é o rastro
     * de onde o registro veio.
     *
     * @param  array<string, array<string, string>>  $contexto
     * @return array<string, mixed>
     */
    private function flatten(array $contexto): array
    {
        return ['origem' => $contexto];
    }

    private function alreadyPopulated(CatalogImportDefinition $def): bool
    {
        return $this->countExisting($def) > 0;
    }

    private function countExisting(CatalogImportDefinition $def): int
    {
        return ExternalRecord::countFor($def->systemId, $def->type);
    }
}
