<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;
use InvalidArgumentException;

/**
 * Validação dos dois formulários que a aplicação recebe (webhook do SAP e
 * resolução de vínculo).
 *
 * Regras suportadas — só as que os formulários usam:
 *
 *   required                     campo obrigatório e não vazio
 *   nullable                     aceita null/"" e para de validar o campo
 *   sometimes                    só valida se o campo veio na requisição
 *   string                       precisa ser texto
 *   boolean                      true/false/1/0/"1"/"0"/"true"/"false"
 *   max:N                        no máximo N caracteres
 *   required_if_accepted:campo   obrigatório quando `campo` for verdadeiro
 *
 * Em caso de erro lança `ValidationException`, que o front controller
 * transforma em 422.
 */
final class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, string>>  $rules     campo => lista de regras
     * @param  array<string, string>  $messages  "campo.regra" => mensagem
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {}

    /**
     * Valida e devolve só os campos declarados nas regras.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        foreach ($this->rules as $field => $rules) {
            $this->applyTo($field, $rules);
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    /** @param  array<int, string>  $rules */
    private function applyTo(string $field, array $rules): void
    {
        $present = array_key_exists($field, $this->data);
        $value = $this->data[$field] ?? null;

        if (in_array('sometimes', $rules, true) && ! $present) {
            return;
        }

        if (in_array('required', $rules, true) && $this->isBlank($value)) {
            $this->fail($field, 'required', "O campo {$field} é obrigatório.");

            return;
        }

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'required_if_accepted:')) {
                $other = substr($rule, strlen('required_if_accepted:'));

                if ($this->isAccepted($this->data[$other] ?? null) && $this->isBlank($value)) {
                    $this->fail($field, 'required_if_accepted', "O campo {$field} é obrigatório quando {$other} está marcado.");

                    return;
                }
            }
        }

        // "nullable" (ou campo simplesmente ausente e não obrigatório): não há
        // mais o que checar.
        if ($this->isBlank($value)) {
            return;
        }

        foreach ($rules as $rule) {
            $this->applyRule($field, $rule, $value);
        }
    }

    private function applyRule(string $field, string $rule, mixed $value): void
    {
        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);

            if (mb_strlen((string) $value) > $max) {
                $this->fail($field, 'max', "O campo {$field} não pode ter mais de {$max} caracteres.");
            }

            return;
        }

        match ($rule) {
            'string' => is_string($value)
                ? null
                : $this->fail($field, 'string', "O campo {$field} precisa ser texto."),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)
                ? null
                : $this->fail($field, 'boolean', "O campo {$field} precisa ser verdadeiro ou falso."),
            'required', 'nullable', 'sometimes' => null,
            default => str_starts_with($rule, 'required_if_accepted:')
                ? null
                : throw new InvalidArgumentException("regra de validação desconhecida: {$rule}"),
        };
    }

    private function fail(string $field, string $rule, string $fallback): void
    {
        $this->errors[$field][] = $this->messages["{$field}.{$rule}"] ?? $fallback;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || (is_array($value) && $value === []);
    }

    private function isAccepted(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
