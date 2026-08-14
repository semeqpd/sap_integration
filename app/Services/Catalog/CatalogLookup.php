<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use InvalidArgumentException;

/**
 * Um arquivo cruzado com o arquivo base, declarado no manifesto como:
 *
 *     "on": "client.customer_corporation_id = corp.id"
 *            ^ campo do lado esquerdo         ^ chave deste arquivo
 */
final readonly class CatalogLookup
{
    public function __construct(
        public string $alias,
        public string $file,
        public string $leftField,   // "client.customer_corporation_id"
        public string $rightColumn, // "id" (coluna deste arquivo)
        public bool $required,
    ) {}

    /** @param  mixed  $data */
    public static function fromArray(mixed $data, string $onde): self
    {
        if (! is_array($data) || ! isset($data['alias'], $data['file'], $data['on'])) {
            throw new InvalidArgumentException("{$onde} precisa de 'alias', 'file' e 'on'.");
        }

        $alias = (string) $data['alias'];

        // "client.customer_corporation_id = corp.id"
        if (! preg_match('/^\s*([\w]+\.[\w]+)\s*=\s*([\w]+)\.([\w]+)\s*$/', (string) $data['on'], $m)) {
            throw new InvalidArgumentException(
                "{$onde}.on deve ter a forma \"base.coluna = {$alias}.coluna\" — recebido: \"{$data['on']}\"."
            );
        }

        if ($m[2] !== $alias) {
            throw new InvalidArgumentException(
                "{$onde}.on: o lado direito deve ser \"{$alias}.coluna\", mas veio \"{$m[2]}.{$m[3]}\"."
            );
        }

        return new self(
            alias: $alias,
            file: (string) $data['file'],
            leftField: $m[1],
            rightColumn: $m[3],
            required: (bool) ($data['required'] ?? true),
        );
    }
}
