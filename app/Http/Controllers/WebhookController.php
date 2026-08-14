<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Http\Requests\SapCustomerWebhookRequest;
use App\Http\Resources\EntityResource;
use App\Http\Resources\LinkResource;
use App\Services\CustomerRegistrar;

/**
 * Webhook de cadastro do SAP.
 *
 * Fora do prefixo /api de propósito: o contrato com o SAP é
 * POST /webhook/sap/customer, igual ao do middleware em Go.
 */
final class WebhookController extends Controller
{
    public function sapCustomer(Request $request): Response
    {
        $event = SapCustomerWebhookRequest::fromRequest($request)->toEvent();

        /** @var CustomerRegistrar $registrar */
        $registrar = $this->service(CustomerRegistrar::class);
        $result = $registrar->handle($event);

        return $this->json([
            'entity' => EntityResource::make($result->entity),
            'links' => LinkResource::collection($result->links),
            'steps' => $result->steps->toArray(),
        ]);
    }
}
