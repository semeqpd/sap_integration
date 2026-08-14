<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Enums\EntityType;
use App\Enums\LinkStatus;

/**
 * Registro canônico — uma linha por "coisa" do mundo real, com N vínculos.
 *
 * @property int $id
 * @property EntityType $type
 * @property string $name
 * @property array<string, mixed>|null $attributes_json
 * @property string|null $created_from
 * @property bool $active
 */
final class Entity extends Model
{
    protected static string $table = 'entities';

    protected static ?string $createdAtColumn = 'created_at';

    protected static ?string $updatedAtColumn = 'updated_at';

    /** Vínculos já carregados — `null` = ainda não foi ao banco. @var array<int, Link>|null */
    private ?array $loadedLinks = null;

    protected static function casts(): array
    {
        return [
            'type' => EntityType::class,
            'active' => 'boolean',
            // A coluna `attributes` (jsonb no schema original) é lida direto,
            // sem acessor: aqui não existe o array interno do Eloquent com que
            // ela colidia no PHP_port.
            'attributes' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @param  array<string, mixed>  $attributes */
    public static function create(array $attributes): self
    {
        return (new self($attributes))->save();
    }

    public static function find(int $id): ?self
    {
        return self::fetchFirst('SELECT * FROM entities WHERE id = ?', [$id]);
    }

    public static function findByName(string $name): ?self
    {
        return self::fetchFirst('SELECT * FROM entities WHERE name = ? ORDER BY id LIMIT 1', [$name]);
    }

    /**
     * Resolve "código X do sistema Y" para a entidade dona — só vínculos
     * fechados contam.
     */
    public static function findByExternalCode(string $systemId, string $externalCode): ?self
    {
        return self::fetchFirst(
            'SELECT e.* FROM entities e
               JOIN links l ON l.entity_id = e.id
              WHERE l.system_id = ? AND l.external_code = ? AND l.status = ?
              ORDER BY e.id
              LIMIT 1',
            [$systemId, $externalCode, LinkStatus::Linked->value],
        );
    }

    /** @return array<int, self> */
    public static function allOrderedById(): array
    {
        return self::fetchAll('SELECT * FROM entities ORDER BY id');
    }

    /**
     * Todas as entidades com os vínculos de cada uma — é o de-para consolidado
     * da tela.
     *
     * Duas queries e o cruzamento em PHP, em vez do `with('links')` do Eloquent:
     * é a mesma coisa, escrita à vista.
     *
     * @return array<int, self>
     */
    public static function allWithLinks(): array
    {
        $entities = self::allOrderedById();

        $byEntity = [];
        foreach (Link::allOrderedById() as $link) {
            $byEntity[$link->entity_id][] = $link;
        }

        foreach ($entities as $entity) {
            $entity->loadedLinks = $byEntity[$entity->id] ?? [];
        }

        return $entities;
    }

    /**
     * Vínculos desta entidade, ordenados por id. Carrega uma vez e guarda.
     *
     * @return array<int, Link>
     */
    public function links(): array
    {
        return $this->loadedLinks ??= Link::forEntity($this->id);
    }

    /** Vínculo desta entidade num sistema, direto do banco. */
    public function linkFor(string $systemId): ?Link
    {
        return Link::forEntityAndSystem($this->id, $systemId);
    }

    /** @param  array<string, mixed>  $attributes */
    public function createLink(array $attributes): Link
    {
        $link = Link::create(['entity_id' => $this->id] + $attributes);

        // O conjunto mudou: a próxima leitura vai ao banco de novo.
        $this->loadedLinks = null;

        return $link;
    }

    /** Código da entidade em um sistema, considerando só vínculo fechado. */
    public function codeIn(string $systemId): ?string
    {
        foreach ($this->links() as $link) {
            if ($link->system_id === $systemId
                && $link->status === LinkStatus::Linked
                && $link->external_code !== null) {
                return $link->external_code;
            }
        }

        return null;
    }

    /** Cria só se ainda não existir uma entidade com este nome (seed idempotente). */
    public static function firstOrCreateByName(string $name, array $attributes): self
    {
        return self::findByName($name) ?? self::create($attributes + ['name' => $name]);
    }
}
