<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Http\Resources\PendingLinkResource;
use App\Models\Link;

/** GET /api/pending — vínculos esperando decisão humana. */
final class PendingLinkController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->json(PendingLinkResource::collection(Link::openWithRelations()));
    }
}
