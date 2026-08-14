<?php

declare(strict_types=1);

use App\Support\Database\Sql;

// Taxa de câmbio fixa, cadastrada à mão, com vigência.
return [
    'up' => [
        'CREATE TABLE exchange_rates (
            id '.Sql::id().',
            currency varchar(3) NOT NULL,
            rate decimal(15,6) NOT NULL,        -- 1 unidade da moeda = X BRL
            effective_from date NOT NULL,
            set_by varchar(120) NULL
        )'.Sql::tableOptions(),

        'CREATE UNIQUE INDEX uq_exchange_rates ON exchange_rates (currency, effective_from)',
    ],

    'down' => [
        'DROP TABLE IF EXISTS exchange_rates',
    ],
];
