<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PageController;

// Uma página só: as três telas (Vínculos, Invoices, Banco) são abas dela e
// conversam com a API em /api.
Router::get('/', [PageController::class, 'index']);

// Health check (o `/up` que o Laravel expunha) — monitoração, não a tela.
Router::get('/up', [HealthController::class, 'show']);
