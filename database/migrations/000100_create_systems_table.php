<?php

declare(strict_types=1);

use App\Enums\SystemType;
use App\Support\Database\CheckConstraint;
use App\Support\Database\Sql;

// Sistemas conectados ao middleware: 'sap', 'stream', 'quality', 'jaz_ph', 'xero_us'.
return [
    'up' => array_merge([
        'CREATE TABLE systems (
            -- Chave natural (string), não auto-incremento: o código do sistema
            -- aparece na API e nas telas, então precisa ser legível.
            id varchar(32) NOT NULL,
            name varchar(120) NOT NULL,
            type varchar(16) NOT NULL,
            currency varchar(3) NULL,   -- nulo para sistemas internos
            active '.Sql::boolean().' NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        )'.Sql::tableOptions(),
    ], CheckConstraint::in('systems', 'type', SystemType::values())),

    'down' => [
        'DROP TABLE IF EXISTS systems',
    ],
];
