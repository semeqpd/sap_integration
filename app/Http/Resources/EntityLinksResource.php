<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Entity;

/** A entidade com todos os seus vínculos — o de-para consolidado da tela. */
final class EntityLinksResource
{
    /** @return array<string, mixed> */
    public static function make(Entity $entity): array
    {
        return [
            'entity' => EntityResource::make($entity),
            'links' => LinkResource::collection($entity->links()),
        ];
    }

    /**
     * @param  array<int, Entity>  $entities
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $entities): array
    {
        return array_values(array_map(self::make(...), $entities));
    }
}
