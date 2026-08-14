<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExternalRecordController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\PendingLinkController;
use App\Http\Controllers\Api\TableController;

// Mesmos caminhos e mesmo contrato JSON do middleware em Go — a tela é a
// mesma, só mudou quem responde.

// Tela 1 — Vínculos
Router::get('/api/entities', [EntityController::class, 'index']);
Router::get('/api/pending', [PendingLinkController::class, 'index']);
Router::get('/api/external-records', [ExternalRecordController::class, 'index']);
Router::post('/api/links/{link}/resolve', [LinkController::class, 'resolve']);

// Tela 2 — Invoices
Router::get('/api/invoices', [InvoiceController::class, 'index']);
Router::post('/api/poll', [InvoiceController::class, 'poll']);
Router::post('/api/invoices/demo', [InvoiceController::class, 'demo']);

// Tela 3 — Banco
Router::get('/api/events', [EventController::class, 'index']);
Router::get('/api/tables', [TableController::class, 'counts']);
Router::get('/api/tables/{name}', [TableController::class, 'rows']);
