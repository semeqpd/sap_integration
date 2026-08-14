<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\WebhookController;

// Entradas de fora, sem prefixo /api (contrato fixado com o SAP): o SAP chama
// a URL crua POST /webhook/sap/customer, igual ao middleware em Go.
Router::post('/webhook/sap/customer', [WebhookController::class, 'sapCustomer']);
