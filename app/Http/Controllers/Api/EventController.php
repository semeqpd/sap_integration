<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Http\Resources\EventResource;
use App\Models\Event;

/** GET /api/events?limit=50 — log do middleware. */
final class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $limit = $request->integer('limit', 50);
        $limit = max(1, min($limit, 500));

        return $this->json(EventResource::collection(Event::recent($limit)));
    }
}
