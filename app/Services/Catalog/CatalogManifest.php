<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use InvalidArgumentException;
use RuntimeException;

/**
 * O arquivo `config.manifest` — a descrição declarativa de como os CSVs de
 * carga inicial viram catálogo (`external_records`).
 *
 * Chaves começando com "_" são comentários e são ignoradas.
 */
final readonly class CatalogManifest
{
    /** @param  array<int, CatalogImportDefinition>  $imports */
    public function __construct(
        public string $directory,
        public array $imports,
    ) {}

    /**
     * Todo diretório de database/init com um config.json é uma carga inicial.
     * Acrescentar um sistema é criar a pasta e o arquivo — nada de código.
     *
     * @return array<int, string>
     */
    public static function discover(string $initPath): array
    {
        $encontrados = glob(rtrim($initPath, '/\\').'/*/config.json') ?: [];

        sort($encontrados);

        return $encontrados;
    }

    public static function load(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("manifesto não encontrado em {$path}");
        }

        $conteudo = (string) file_get_contents($path);
        $dados = json_decode($conteudo, true);

        if (! is_array($dados)) {
            throw new RuntimeException(
                "manifesto {$path} não é um JSON válido: ".json_last_error_msg()
            );
        }

        if (! isset($dados['imports']) || ! is_array($dados['imports'])) {
            throw new InvalidArgumentException("manifesto {$path} precisa da lista 'imports'.");
        }

        $imports = [];
        foreach (array_values($dados['imports']) as $i => $import) {
            if (! is_array($import)) {
                throw new InvalidArgumentException("imports[{$i}] deveria ser um objeto.");
            }
            $imports[] = CatalogImportDefinition::fromArray($import, $i);
        }

        return new self(dirname($path), $imports);
    }

    /** @return array<int, CatalogImportDefinition> */
    public function enabledImports(): array
    {
        return array_values(array_filter(
            $this->imports,
            static fn (CatalogImportDefinition $i): bool => $i->enabled,
        ));
    }
}
