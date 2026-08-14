<?php

declare(strict_types=1);

use App\Enums\LinkStatus;
use App\Support\Database\CheckConstraint;
use App\Support\Database\Sql;

// De-para de identidade: "esta entidade é o código X no sistema Y".
return [
    'up' => array_merge([
        'CREATE TABLE links (
            id '.Sql::id().',
            entity_id '.Sql::foreignId().' NOT NULL,
            entity_type varchar(24) NOT NULL,             -- cópia de entities.type
            system_id varchar(32) NOT NULL,
            external_code varchar(191) NULL,              -- nulo enquanto pending
            external_name varchar(255) NULL,
            status varchar(20) NOT NULL DEFAULT \''.LinkStatus::Pending->value.'\',
            source varchar(12) NOT NULL,                  -- seed | manual | auto
            linked_by varchar(120) NULL,
            last_synced_at '.Sql::timestampNullable().',
            created_at '.Sql::timestampNow().',
            CONSTRAINT fk_links_entity FOREIGN KEY (entity_id) REFERENCES entities (id),
            CONSTRAINT fk_links_system FOREIGN KEY (system_id) REFERENCES systems (id)
        )'.Sql::tableOptions(),

        // Um mesmo código externo aponta para uma única entidade.
        //
        // No Postgres isso era um índice único parcial (WHERE external_code IS
        // NOT NULL). MySQL e SQLite não tratam NULL como valor em índice único
        // — várias pendências sem código convivem — então o UNIQUE simples
        // reproduz exatamente a mesma regra.
        'CREATE UNIQUE INDEX uq_links_code ON links (system_id, entity_type, external_code)',
        'CREATE INDEX idx_links_entity ON links (entity_id)',
        'CREATE INDEX idx_links_status ON links (status)',
    ],
        CheckConstraint::in('links', 'status', LinkStatus::values()),
        CheckConstraint::in('links', 'source', ['seed', 'manual', 'auto']),
    ),

    'down' => [
        'DROP TABLE IF EXISTS links',
    ],
];
