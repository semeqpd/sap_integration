<?php

declare(strict_types=1);

use App\Enums\EntityType;
use App\Support\Database\CheckConstraint;
use App\Support\Database\Sql;

// Registro canônico: uma linha por "coisa" do mundo real.
return [
    'up' => array_merge([
        'CREATE TABLE entities (
            id '.Sql::id().',
            type varchar(24) NOT NULL,
            name varchar(255) NOT NULL,
            attributes '.Sql::json().' NULL,
            created_from varchar(32) NULL,
            active '.Sql::boolean().' NOT NULL DEFAULT 1,
            created_at '.Sql::timestampNullable().',
            updated_at '.Sql::timestampNullable().',
            CONSTRAINT fk_entities_created_from FOREIGN KEY (created_from) REFERENCES systems (id)
        )'.Sql::tableOptions(),

        // O Postgres usava índice parcial (WHERE active); no MySQL não existe
        // índice parcial — o composto (active, type) cobre a mesma consulta
        // sem perda prática.
        'CREATE INDEX idx_entities_type ON entities (active, type)',
    ], CheckConstraint::in('entities', 'type', EntityType::values())),

    'down' => [
        'DROP TABLE IF EXISTS entities',
    ],
];
