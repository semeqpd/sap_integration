<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\LinkStatus;
use App\Support\Database\Sql;

/**
 * Caixa de entrada humana: derivada do estado real, nunca dessincroniza.
 *
 * A tela consulta as tabelas direto; a view existe para inspeção manual e
 * relatórios, mantendo a paridade com o schema em Postgres. A coluna `system`
 * do original virou `system_name` — `SYSTEM` é palavra reservada no MySQL 8.
 */
$openLinks = Sql::quotedList([LinkStatus::Pending->value, LinkStatus::Error->value]);
$openInvoices = Sql::quotedList([InvoiceStatus::Blocked->value, InvoiceStatus::Error->value]);

$invoiceLabel = Sql::concat("'invoice '", 'i.external_code');

return [
    'up' => [
        // Sem este DROP, rodar `migrate.php --fresh` duas vezes seguidas
        // quebrava: a view sobrevivia ao drop das tabelas e o CREATE abaixo
        // falhava com "already exists". Foi um dos defeitos corrigidos na
        // validação do PHP_port — a correção continua aqui.
        'DROP VIEW IF EXISTS pending_queue',

        "CREATE VIEW pending_queue AS
         SELECT 'link' AS kind, l.id AS ref_id, e.type AS entity_type,
                e.name AS description, s.name AS system_name, l.created_at AS since
           FROM links l
           JOIN entities e ON e.id = l.entity_id
           JOIN systems  s ON s.id = l.system_id
          WHERE l.status IN ({$openLinks})
         UNION ALL
         SELECT 'invoice', i.id, 'invoice',
                COALESCE(i.block_reason, {$invoiceLabel}), s.name, i.received_at
           FROM invoice_staging i
           JOIN systems s ON s.id = i.system_id
          WHERE i.status IN ({$openInvoices})",
    ],

    'down' => [
        'DROP VIEW IF EXISTS pending_queue',
    ],
];
