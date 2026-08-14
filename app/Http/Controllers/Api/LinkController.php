<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Requests\ResolveLinkRequest;
use App\Http\Resources\EntityResource;
use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Services\LinkResolver;

/** POST /api/links/{link}/resolve — fecha uma pendência de vínculo. */
final class LinkController extends Controller
{
    public function resolve(Request $request, string $link): Response
    {
        $model = Link::find((int) $link)
            ?? throw new NotFoundException("vínculo {$link} não existe");

        $data = ResolveLinkRequest::fromRequest($request)->toData();

        /** @var LinkResolver $resolver */
        $resolver = $this->service(LinkResolver::class);
        $result = $resolver->handle($model, $data);

        return $this->json([
            'entity' => EntityResource::make($result->entity),
            'links' => LinkResource::collection($result->links),
            'steps' => $result->steps->toArray(),
        ]);
    }
}
