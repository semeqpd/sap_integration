<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Core\Request;
use App\Core\Validator;
use App\Services\Data\SapCustomerEvent;

/** Corpo do POST /webhook/sap/customer (o SAP manda o BP e o país da filial). */
final readonly class SapCustomerWebhookRequest
{
    /** @param  array<string, mixed>  $validated */
    private function __construct(private array $validated) {}

    /** Valida a requisição; em erro lança ValidationException (vira 422). */
    public static function fromRequest(Request $request): self
    {
        $validated = (new Validator($request->all(), self::rules(), self::messages()))->validate();

        return new self($validated);
    }

    public function toEvent(): SapCustomerEvent
    {
        return SapCustomerEvent::fromArray($this->validated);
    }

    /** @return array<string, array<int, string>> */
    private static function rules(): array
    {
        return [
            'CardCode' => ['required', 'string', 'max:191'],
            'CardName' => ['required', 'string', 'max:255'],
            'Country' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, string> */
    private static function messages(): array
    {
        return [
            'CardCode.required' => 'CardCode é obrigatório.',
            'CardName.required' => 'CardName é obrigatório.',
        ];
    }
}
