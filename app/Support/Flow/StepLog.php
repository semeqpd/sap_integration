<?php

declare(strict_types=1);

namespace App\Support\Flow;

use App\Enums\StepOp;
use Countable;
use JsonSerializable;

/**
 * Coletor dos passos de uma ação.
 *
 * Todo serviço recebe (ou devolve) um StepLog em vez de montar array solto:
 * a tela mostra exatamente a sequência registrada aqui, na ordem em que
 * aconteceu.
 */
final class StepLog implements Countable, JsonSerializable
{
    /** @var array<int, Step> */
    private array $steps = [];

    public static function make(): self
    {
        return new self;
    }

    /** Passo narrativo, sem detalhe de tabela. */
    public function note(string $desc): self
    {
        $this->steps[] = new Step($desc);

        return $this;
    }

    public function select(string $table, string $desc): self
    {
        return $this->db(StepOp::Select, $table, $desc);
    }

    public function insert(string $table, string $desc): self
    {
        return $this->db(StepOp::Insert, $table, $desc);
    }

    public function update(string $table, string $desc): self
    {
        return $this->db(StepOp::Update, $table, $desc);
    }

    /** Chamada a um sistema externo (SAP, Jaz, Xero). */
    public function api(string $service, string $desc): self
    {
        return $this->db(StepOp::Api, $service, $desc);
    }

    public function db(StepOp $op, string $table, string $desc): self
    {
        $this->steps[] = new Step($desc, $table, $op);

        return $this;
    }

    /** Anexa os passos de outro log (ex.: o poll agrega o de cada invoice). */
    public function merge(self $other): self
    {
        foreach ($other->steps as $step) {
            $this->steps[] = $step;
        }

        return $this;
    }

    /** @return array<int, Step> */
    public function all(): array
    {
        return $this->steps;
    }

    public function count(): int
    {
        return count($this->steps);
    }

    /** @return array<int, array{table: string|null, op: string|null, desc: string}> */
    public function toArray(): array
    {
        return array_map(static fn (Step $step): array => $step->toArray(), $this->steps);
    }

    /** @return array<int, array{table: string|null, op: string|null, desc: string}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
