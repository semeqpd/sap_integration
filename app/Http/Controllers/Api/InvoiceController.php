<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Http\Resources\InvoiceResource;
use App\Models\StagedInvoice;
use App\Services\DemoInvoiceInjector;
use App\Services\InvoicePoller;

/** Tela de invoices: listagem, poll manual e injeção de invoice de teste. */
final class InvoiceController extends Controller
{
    /** GET /api/invoices */
    public function index(Request $request): Response
    {
        return $this->json(InvoiceResource::collection(StagedInvoice::recent(200)));
    }

    /** POST /api/poll — "Verificar agora". */
    public function poll(Request $request): Response
    {
        /** @var InvoicePoller $poller */
        $poller = $this->service(InvoicePoller::class);
        $result = $poller->pollAll();

        return $this->json([
            'new' => $result->new,
            'steps' => $result->steps->toArray(),
        ]);
    }

    /** POST /api/invoices/demo — invoice sintética pelo caminho real. */
    public function demo(Request $request): Response
    {
        /** @var DemoInvoiceInjector $injector */
        $injector = $this->service(DemoInvoiceInjector::class);
        $result = $injector->inject();

        return $this->json([
            'reference' => $result->reference,
            'steps' => $result->steps->toArray(),
        ]);
    }
}
