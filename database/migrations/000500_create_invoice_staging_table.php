<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Support\Database\CheckConstraint;
use App\Support\Database\Sql;

// Invoices das filiais: entram brutas, nunca se perdem.
return [
    'up' => array_merge([
        'CREATE TABLE invoice_staging (
            id '.Sql::id().',
            system_id varchar(32) NOT NULL,
            external_code varchar(191) NOT NULL,
            payload '.Sql::json().' NOT NULL,             -- JSON original da filial
            type varchar(12) NULL,                        -- sale | purchase
            status varchar(16) NOT NULL DEFAULT \''.InvoiceStatus::Received->value.'\',
            block_reason text NULL,
            currency varchar(3) NULL,
            source_amount decimal(15,2) NULL,
            document_date date NULL,
            exchange_rate_used decimal(15,6) NULL,
            amount_brl decimal(15,2) NULL,
            sap_doc_entry '.Sql::unsignedInteger().' NULL,
            attempts '.Sql::unsignedInteger().' NOT NULL DEFAULT 0,
            received_at '.Sql::timestampNow().',
            posted_at '.Sql::timestampNullable().',
            CONSTRAINT fk_invoice_staging_system FOREIGN KEY (system_id) REFERENCES systems (id)
        )'.Sql::tableOptions(),

        // Idempotência do poll: a mesma invoice nunca entra duas vezes.
        'CREATE UNIQUE INDEX uq_invoice_staging ON invoice_staging (system_id, external_code)',
        'CREATE INDEX idx_invoice_status ON invoice_staging (status)',
    ],
        CheckConstraint::in('invoice_staging', 'status', InvoiceStatus::values()),
        CheckConstraint::in('invoice_staging', 'type', ['sale', 'purchase'], nullable: true),
    ),

    'down' => [
        'DROP TABLE IF EXISTS invoice_staging',
    ],
];
