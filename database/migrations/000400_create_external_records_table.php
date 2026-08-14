<?php

declare(strict_types=1);

use App\Support\Database\Sql;

// Cache do que existe em cada sistema — alimenta os dropdowns da tela manual.
return [
    'up' => [
        'CREATE TABLE external_records (
            id '.Sql::id().',
            system_id varchar(32) NOT NULL,
            type varchar(24) NOT NULL,
            external_code varchar(191) NOT NULL,
            name varchar(255) NULL,
            data '.Sql::json().' NULL,
            first_seen_at '.Sql::timestampNow().',
            last_seen_at '.Sql::timestampNow().',
            CONSTRAINT fk_external_records_system FOREIGN KEY (system_id) REFERENCES systems (id)
        )'.Sql::tableOptions(),

        // Chave do upsert do sync de catálogo.
        'CREATE UNIQUE INDEX uq_external_records ON external_records (system_id, type, external_code)',
    ],

    'down' => [
        'DROP TABLE IF EXISTS external_records',
    ],
];
