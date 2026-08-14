<?php

declare(strict_types=1);

namespace App\Core;

use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Base enxuta dos models. **Não é um ORM.**
 *
 * Ela cuida só do que é igual em todo model:
 *
 *   - guardar os valores das colunas;
 *   - aplicar os `casts` na leitura e na escrita (enum, data, booleano, JSON,
 *     decimal);
 *   - `save()`: INSERT quando não tem chave primária, UPDATE quando tem.
 *
 * Consulta é responsabilidade de cada model, em SQL explícito e com um método
 * nomeado por caso de uso (`Link::openWithRelations()`, `StagedInvoice::seen()`).
 * Não existe `->where()->orderBy()->get()` genérico aqui de propósito: quem
 * lê o código vê a query, não uma cadeia de chamadas.
 *
 * Sobre os `casts` de enum: eles são o que impede um valor inválido de chegar
 * ao banco, antes mesmo do CHECK constraint.
 */
abstract class Model
{
    /** Nome da tabela. */
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /** `false` para chave natural em texto (ex.: `systems.id`). */
    protected static bool $incrementing = true;

    /** Colunas de carimbo que o model preenche sozinho; `null` = a tabela não tem. */
    protected static ?string $createdAtColumn = null;

    protected static ?string $updatedAtColumn = null;

    /** Valores como estão (ou vão) no banco. */
    protected array $attributes = [];

    /** `true` quando a linha já existe no banco (veio de um SELECT ou já foi salva). */
    protected bool $exists = false;

    /**
     * Coluna => cast. Valores aceitos:
     * `boolean`, `integer`, `array` (JSON), `datetime`, `date`, `decimal:N`
     * ou o nome de uma classe de enum.
     *
     * @return array<string, string>
     */
    protected static function casts(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $attributes */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Monta o objeto a partir de uma linha crua do banco.
     *
     * @param  array<string, mixed>  $row
     */
    public static function hydrate(array $row): static
    {
        $model = new static;
        $model->attributes = $row;
        $model->exists = true;

        return $model;
    }

    /** @param  array<string, mixed>  $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $column => $value) {
            $this->__set($column, $value);
        }

        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->fromDatabase($name, $this->attributes[$name] ?? null);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $this->toDatabase($name, $value);
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getKey(): mixed
    {
        return $this->__get(static::$primaryKey);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    /** @return array<string, mixed> os valores como estão no banco */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** INSERT se ainda não existe, UPDATE pela chave primária se já existe. */
    public function save(): static
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        if ($this->exists) {
            if (static::$updatedAtColumn !== null) {
                $this->attributes[static::$updatedAtColumn] = $now;
            }

            $this->update();

            return $this;
        }

        foreach ([static::$createdAtColumn, static::$updatedAtColumn] as $column) {
            if ($column !== null && ! isset($this->attributes[$column])) {
                $this->attributes[$column] = $now;
            }
        }

        $this->insert();
        $this->exists = true;

        return $this;
    }

    /** Recarrega do banco (usado pelos testes depois de uma ação). */
    public function refresh(): static
    {
        $row = Database::selectOne(
            'SELECT * FROM '.static::$table.' WHERE '.static::$primaryKey.' = ?',
            [$this->attributes[static::$primaryKey] ?? null],
        );

        if ($row !== null) {
            $this->attributes = $row;
        }

        return $this;
    }

    // -----------------------------------------------------------------------
    // Consulta — usado pelos métodos nomeados de cada model
    // -----------------------------------------------------------------------

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int, static>
     */
    protected static function fetchAll(string $sql, array $bindings = []): array
    {
        return array_map(static::hydrate(...), Database::select($sql, $bindings));
    }

    /** @param  array<int|string, mixed>  $bindings */
    protected static function fetchFirst(string $sql, array $bindings = []): ?static
    {
        $row = Database::selectOne($sql, $bindings);

        return $row === null ? null : static::hydrate($row);
    }

    /**
     * Total de linhas da tabela, opcionalmente filtrado.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    public static function count(string $where = '', array $bindings = []): int
    {
        $sql = 'SELECT COUNT(*) FROM '.static::$table.($where !== '' ? " WHERE {$where}" : '');

        return (int) Database::scalar($sql, $bindings);
    }

    public static function tableName(): string
    {
        return static::$table;
    }

    // -----------------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------------

    private function insert(): void
    {
        $columns = $this->attributes;

        // Chave auto-incremento não vai no INSERT.
        if (static::$incrementing) {
            unset($columns[static::$primaryKey]);
        }

        $names = array_keys($columns);
        $placeholders = array_fill(0, count($names), '?');

        Database::execute(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                static::$table,
                implode(', ', $names),
                implode(', ', $placeholders),
            ),
            array_values($columns),
        );

        if (static::$incrementing) {
            $this->attributes[static::$primaryKey] = (int) Database::lastInsertId();
        }
    }

    private function update(): void
    {
        $columns = $this->attributes;
        $key = $columns[static::$primaryKey] ?? null;
        unset($columns[static::$primaryKey]);

        if ($columns === []) {
            return;
        }

        $assignments = implode(', ', array_map(
            static fn (string $column): string => "{$column} = ?",
            array_keys($columns),
        ));

        Database::execute(
            sprintf('UPDATE %s SET %s WHERE %s = ?', static::$table, $assignments, static::$primaryKey),
            [...array_values($columns), $key],
        );
    }

    // -----------------------------------------------------------------------
    // Casts
    // -----------------------------------------------------------------------

    /** Banco -> PHP. */
    private function fromDatabase(string $name, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Chave auto-incremento é sempre inteiro, mesmo quando o driver
        // devolve string.
        if ($name === static::$primaryKey && static::$incrementing) {
            return (int) $value;
        }

        $cast = static::casts()[$name] ?? null;

        if ($cast === null) {
            return $value;
        }

        if (is_a($cast, BackedEnum::class, allow_string: true)) {
            return $value instanceof BackedEnum ? $value : $cast::from($value);
        }

        if (str_starts_with($cast, 'decimal:')) {
            return number_format((float) $value, (int) substr($cast, 8), '.', '');
        }

        return match ($cast) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'array' => is_string($value) ? (json_decode($value, true) ?: []) : (array) $value,
            'datetime' => $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value),
            'date' => ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value))->startOfDay(),
            default => $value,
        };
    }

    /** PHP -> banco. */
    private function toDatabase(string $name, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return (static::casts()[$name] ?? null) === 'date'
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
