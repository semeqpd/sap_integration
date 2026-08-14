<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Dados de entrada inválidos.
 *
 * Vira 422 com o mesmo corpo que o Laravel devolvia, que é o que o
 * `public/js/core/api.js` já sabe ler:
 *
 *     {"message": "<primeira mensagem>", "errors": {"campo": ["mensagem", ...]}}
 */
class ValidationException extends RuntimeException
{
    /** @param  array<string, array<int, string>>  $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($this->firstMessage());
    }

    /**
     * Atalho para o erro de regra cruzada, escrito na mão pelo serviço.
     *
     * @param  array<string, string|array<int, string>>  $messages
     */
    public static function withMessages(array $messages): self
    {
        $errors = [];

        foreach ($messages as $field => $message) {
            $errors[$field] = is_array($message) ? array_values($message) : [$message];
        }

        return new self($errors);
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstMessage(): string
    {
        foreach ($this->errors as $messages) {
            foreach ($messages as $message) {
                return $message;
            }
        }

        return 'Os dados enviados são inválidos.';
    }
}
