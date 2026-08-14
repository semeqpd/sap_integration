<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use InvalidArgumentException;

/**
 * Um item da lista `imports` do config.manifest — a receita de como um par de
 * CSVs vira linhas de `external_records`.
 */
final readonly class CatalogImportDefinition
{
    /**
     * @param  array<int, CatalogLookup>  $lookups
     * @param  array<int, string>  $skipWhenBlank  campos "alias.coluna"
     * @param  array<string, array<int, string>>  $where  "alias.coluna" => valores aceitos
     */
    public function __construct(
        public bool $enabled,
        public string $systemId,
        public string $type,
        public string $baseAlias,
        public string $baseFile,
        public array $lookups,
        public string $externalCodeTemplate,
        public string $nameTemplate,
        public array $skipWhenBlank,
        public array $where,
        public string $delimiter,
        public string $enclosure,
        public bool $overwriteExisting,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data, int $index): self
    {
        $onde = "imports[{$index}]";

        $base = $data['base'] ?? null;
        if (! is_array($base) || ! isset($base['alias'], $base['file'])) {
            throw new InvalidArgumentException("{$onde}.base precisa de 'alias' e 'file'.");
        }

        foreach (['system_id', 'type', 'external_code', 'name'] as $obrigatorio) {
            if (! isset($data[$obrigatorio]) || ! is_string($data[$obrigatorio])) {
                throw new InvalidArgumentException("{$onde}.{$obrigatorio} é obrigatório.");
            }
        }

        $lookups = [];
        foreach ($data['lookups'] ?? [] as $i => $lookup) {
            $lookups[] = CatalogLookup::fromArray($lookup, "{$onde}.lookups[{$i}]");
        }

        $csv = $data['csv'] ?? [];

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            systemId: $data['system_id'],
            type: $data['type'],
            baseAlias: (string) $base['alias'],
            baseFile: (string) $base['file'],
            lookups: $lookups,
            externalCodeTemplate: $data['external_code'],
            nameTemplate: $data['name'],
            skipWhenBlank: array_values(array_map('strval', $data['skip_when_blank'] ?? [])),
            where: self::normalizeWhere($data['where'] ?? [], $onde),
            delimiter: (string) ($csv['delimiter'] ?? ','),
            enclosure: (string) ($csv['enclosure'] ?? '"'),
            // Padrão: não sobrescreve. Registro que já existe fica como está.
            overwriteExisting: ($data['on_conflict'] ?? 'skip') === 'update',
        );
    }

    /**
     * @param  mixed  $where
     * @return array<string, array<int, string>>
     */
    private static function normalizeWhere(mixed $where, string $onde): array
    {
        if (! is_array($where)) {
            throw new InvalidArgumentException("{$onde}.where precisa ser um objeto campo => [valores].");
        }

        $normalizado = [];

        foreach ($where as $campo => $valores) {
            $normalizado[(string) $campo] = array_map('strval', is_array($valores) ? $valores : [$valores]);
        }

        return $normalizado;
    }

    /** Todos os aliases válidos nesta importação (base + lookups). */
    public function aliases(): array
    {
        return array_merge(
            [$this->baseAlias],
            array_map(static fn (CatalogLookup $l): string => $l->alias, $this->lookups),
        );
    }
}
