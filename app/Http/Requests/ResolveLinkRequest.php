<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Validator;
use App\Services\Data\ResolveLinkData;

/** Decisão da tela ao fechar uma pendência de vínculo. */
final readonly class ResolveLinkRequest
{
    /** @param  array<string, mixed>  $input */
    private function __construct(private array $input) {}

    public static function fromRequest(Request $request): self
    {
        $input = $request->all();

        (new Validator($input, self::rules()))->validate();

        // Regra cruzada: dois caminhos possíveis, escolher do catálogo
        // (external_code) ou cadastrar novo (create_new + external_name).
        // Nenhum dos dois preenchido é erro.
        if (! $request->boolean('create_new') && trim((string) ($input['external_code'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'external_code' => 'Selecione um registro do catálogo ou use "adicionar novo".',
            ]);
        }

        return new self($input);
    }

    public function toData(): ResolveLinkData
    {
        return ResolveLinkData::fromArray($this->input);
    }

    /** @return array<string, array<int, string>> */
    private static function rules(): array
    {
        return [
            'create_new' => ['sometimes', 'boolean'],
            'external_code' => ['nullable', 'string', 'max:191'],
            'external_name' => ['required_if_accepted:create_new', 'nullable', 'string', 'max:255'],
            'linked_by' => ['nullable', 'string', 'max:120'],
        ];
    }
}
