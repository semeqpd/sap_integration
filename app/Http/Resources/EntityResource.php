<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Entity;

/**
 * A entidade sozinha (sem vínculos) — usada nas respostas de ação.
 *
 * Um "Resource" aqui é só uma função que dá forma ao array que vira JSON:
 * o contrato com a tela mora neste arquivo, não espalhado pelos controllers.
 */
final class EntityResource
{
    /** @return array<string, mixed> */
    public static function make(Entity $entity): array
    {
        return [
            'id' => $entity->id,
            'type' => $entity->type->value,
            'name' => $entity->name,
            'created_from' => $entity->created_from ?? '',
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
