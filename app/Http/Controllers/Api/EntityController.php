<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Http\Resources\EntityLinksResource;
use App\Models\Entity;

/** GET /api/entities — o de-para consolidado. */
final class EntityController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->json(EntityLinksResource::collection(Entity::allWithLinks()));
    }
}
