<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Enums\LinkStatus;
use Carbon\Carbon;

/**
 * De-para de identidade: "esta entidade é o código X no sistema Y".
 *
 * @property int $id
 * @property int $entity_id
 * @property string $entity_type
 * @property string $system_id
 * @property string|null $external_code
 * @property string|null $external_name
 * @property LinkStatus $status
 * @property string $source
 * @property string|null $linked_by
 * @property Carbon|null $created_at
 */
final class Link extends Model
{
    protected static string $table = 'links';

    // A tabela só tem created_at: o vínculo é fechado por UPDATE pontual,
    // rastreado em linked_by/last_synced_at.
    protected static ?string $createdAtColumn = 'created_at';

    private ?Entity $loadedEntity = null;

    private ?System $loadedSystem = null;

    protected static function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'status' => LinkStatus::class,
            'last_synced_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @param  array<string, mixed>  $attributes */
    public static function create(array $attributes): self
    {
        return (new self($attributes))->save();
    }

    public static function find(int $id): ?self
    {
        return self::fetchFirst('SELECT * FROM links WHERE id = ?', [$id]);
    }

    /** @return array<int, self> */
    public static function allOrderedById(): array
    {
        return self::fetchAll('SELECT * FROM links ORDER BY id');
    }

    /** @return array<int, self> */
    public static function forEntity(int $entityId): array
    {
        return self::fetchAll('SELECT * FROM links WHERE entity_id = ? ORDER BY id', [$entityId]);
    }

    public static function forEntityAndSystem(int $entityId, string $systemId): ?self
    {
        return self::fetchFirst(
            'SELECT * FROM links WHERE entity_id = ? AND system_id = ? ORDER BY id LIMIT 1',
            [$entityId, $systemId],
        );
    }

    public static function findByCode(string $systemId, string $entityType, string $externalCode): ?self
    {
        return self::fetchFirst(
            'SELECT * FROM links WHERE system_id = ? AND entity_type = ? AND external_code = ? LIMIT 1',
            [$systemId, $entityType, $externalCode],
        );
    }

    /**
     * A fila da tela: vínculos que ainda esperam decisão de uma pessoa, já com
     * a entidade e o sistema de cada um.
     *
     * @return array<int, self>
     */
    public static function openWithRelations(): array
    {
        // array_values: `openValues()` sai de um array_filter e traz as chaves
        // originais do enum (0, 2, 4). O PDO usa chave inteira como posição do
        // parâmetro, então uma lista com buracos quebra o bind.
        $open = array_values(LinkStatus::openValues());
        $placeholders = implode(', ', array_fill(0, count($open), '?'));

        $links = self::fetchAll("SELECT * FROM links WHERE status IN ({$placeholders}) ORDER BY id", $open);

        if ($links === []) {
            return [];
        }

        // Carrega entidades e sistemas de uma vez, em vez de uma query por linha.
        $entities = [];
        foreach (Entity::allOrderedById() as $entity) {
            $entities[$entity->id] = $entity;
        }

        $systems = [];
        foreach (System::all() as $system) {
            $systems[$system->id] = $system;
        }

        foreach ($links as $link) {
            $link->loadedEntity = $entities[$link->entity_id] ?? null;
            $link->loadedSystem = $systems[$link->system_id] ?? null;
        }

        return $links;
    }

    public function entity(): ?Entity
    {
        return $this->loadedEntity ??= Entity::find($this->entity_id);
    }

    public function system(): ?System
    {
        return $this->loadedSystem ??= System::find($this->system_id);
    }

    /** Fecha o vínculo com o código escolhido na tela. */
    public function resolveTo(string $externalCode, ?string $externalName, string $linkedBy): void
    {
        $this->external_code = $externalCode;
        $this->external_name = $externalName;
        $this->status = LinkStatus::Linked;
        $this->linked_by = $linkedBy;
        $this->last_synced_at = Carbon::now();
        $this->save();
    }

    /** Cria só se ainda não existir o mesmo código no mesmo sistema (seed idempotente). */
    public static function firstOrCreateByCode(
        string $systemId,
        string $entityType,
        string $externalCode,
        array $attributes,
    ): self {
        return self::findByCode($systemId, $entityType, $externalCode) ?? self::create($attributes + [
            'system_id' => $systemId,
            'entity_type' => $entityType,
            'external_code' => $externalCode,
        ]);
    }
}
