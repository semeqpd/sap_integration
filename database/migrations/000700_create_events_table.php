<?php

declare(strict_types=1);

use App\Enums\EventDirection;
use App\Support\Database\CheckConstraint;
use App\Support\Database\Sql;

// Log de tudo que o middleware fez ou recebeu.
// Vem por último porque referencia `entities` E `invoice_staging`.
return [
    'up' => array_merge([
        'CREATE TABLE events (
            id '.Sql::id().',
            occurred_at '.Sql::timestampNow().',
            system_id varchar(32) NULL,
            direction varchar(10) NOT NULL,
            action varchar(64) NOT NULL,
            entity_id '.Sql::foreignId().' NULL,
            invoice_id '.Sql::foreignId().' NULL,
            success '.Sql::boolean().' NOT NULL,
            details '.Sql::json().' NULL,
            CONSTRAINT fk_events_entity FOREIGN KEY (entity_id) REFERENCES entities (id),
            CONSTRAINT fk_events_invoice FOREIGN KEY (invoice_id) REFERENCES invoice_staging (id),
            CONSTRAINT fk_events_system FOREIGN KEY (system_id) REFERENCES systems (id)
        )'.Sql::tableOptions(),

        'CREATE INDEX idx_events_time ON events (occurred_at)',
    ], CheckConstraint::in('events', 'direction', EventDirection::values())),

    'down' => [
        'DROP TABLE IF EXISTS events',
    ],
];
