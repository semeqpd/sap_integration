<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Registro de objetos compartilhados (singletons).
 *
 * **Não é um container de injeção de dependência.** Não há autowiring, não há
 * reflection, não há anotação: em `app/bootstrap.php` cada objeto é construído
 * à mão, na ordem certa, recebendo as dependências no construtor. Aqui só ficam
 * as fábricas e as instâncias já criadas.
 *
 * Quem precisa de um serviço pede por classe:
 *
 *     Container::get(InvoicePoller::class)->pollAll();
 */
final class Container
{
    /** @var array<string, callable(): object> */
    private static array $factories = [];

    /** @var array<string, object> */
    private static array $instances = [];

    /**
     * Registra como construir um objeto. A fábrica roda no máximo uma vez —
     * na primeira chamada a `get()`.
     *
     * @param  callable(): object  $factory
     */
    public static function singleton(string $id, callable $factory): void
    {
        self::$factories[$id] = $factory;
        unset(self::$instances[$id]);
    }

    /** Registra um objeto já pronto (é o que os testes usam para trocar um serviço por um fake). */
    public static function instance(string $id, object $object): void
    {
        self::$instances[$id] = $object;
    }

    public static function get(string $id): object
    {
        if (isset(self::$instances[$id])) {
            return self::$instances[$id];
        }

        if (isset(self::$factories[$id])) {
            return self::$instances[$id] = (self::$factories[$id])();
        }

        // Sem fábrica registrada: só resolve classe que se constrói sozinha
        // (é o caso dos controllers, que pegam o que precisam de dentro do
        // método). Qualquer classe com dependência precisa ser registrada
        // explicitamente no bootstrap.
        if (! class_exists($id)) {
            throw new RuntimeException("nada registrado no container para \"{$id}\" e a classe não existe");
        }

        try {
            return self::$instances[$id] = new $id;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "\"{$id}\" precisa de dependências — registre-a em app/bootstrap.php: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public static function has(string $id): bool
    {
        return isset(self::$instances[$id]) || isset(self::$factories[$id]);
    }

    /** Descarta a instância já criada; a próxima chamada a `get()` reconstrói. */
    public static function forget(string $id): void
    {
        unset(self::$instances[$id]);
    }

    /** Descarta só as instâncias, mantendo as fábricas do bootstrap (usado entre testes). */
    public static function forgetInstances(): void
    {
        self::$instances = [];
    }
}
