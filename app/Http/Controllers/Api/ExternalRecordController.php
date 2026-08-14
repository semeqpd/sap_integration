<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Enums\EntityType;
use App\Http\Resources\ExternalRecordResource;
use App\Models\ExternalRecord;

/** GET /api/external-records?system=jaz_ph&type=customer — catálogo do dropdown. */
final class ExternalRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = (new Validator($request->all(), [
            'system' => ['required', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'max:24'],
        ]))->validate();

        $records = ExternalRecord::catalogFor(
            (string) $validated['system'],
            (string) ($validated['type'] ?? EntityType::Customer->value),
        );

        return $this->json(ExternalRecordResource::collection($records));
    }
}
