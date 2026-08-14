<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Cache;
use App\Core\Container;
use App\Core\Database;
use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Throwable;

/**
 * Base dos testes que tocam o banco.
 *
 * Reproduz o que o `RefreshDatabase` do Laravel fazia, sem a lib: o schema é
 * migrado **uma vez** (SQLite `:memory:` vive enquanto a conexão viver) e cada
 * teste roda dentro de uma transação que leva rollback no fim. Um teste nunca
 * enxerga o que o outro gravou.
 */
abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->migrate();
            self::$migrated = true;
        }

        // Serviços voltam ao que o bootstrap registrou (um teste pode ter
        // trocado uma filial por um fake).
        Container::forgetInstances();
        Cache::flush();

        Database::beginTransaction();
    }

    protected function tearDown(): void
    {
        Database::rollBack();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Requisições — sem subir servidor: monta a Request e chama o Router.
    // -----------------------------------------------------------------------

    protected function get(string $uri): TestResponse
    {
        return $this->call('GET', $uri);
    }

    protected function getJson(string $uri): TestResponse
    {
        return $this->call('GET', $uri, headers: ['Accept' => 'application/json']);
    }

    /** @param  array<string, mixed>  $body */
    protected function postJson(string $uri, array $body = []): TestResponse
    {
        return $this->call('POST', $uri, $body, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    protected function call(string $method, string $uri, array $body = [], array $headers = []): TestResponse
    {
        try {
            $response = Router::dispatch(Request::create($method, $uri, $body, $headers));
        } catch (Throwable $e) {
            // Mesmo tratamento do public/index.php: o 422 que o teste vê é o
            // 422 que o navegador veria.
            $response = ExceptionHandler::toResponse($e);
        }

        return new TestResponse($response);
    }

    // -----------------------------------------------------------------------
    // Banco
    // -----------------------------------------------------------------------

    /**
     * `systems` é dado estrutural (as FKs apontam para ele): todo teste que
     * mexe no banco precisa dele carregado.
     */
    protected function seedSystems(): void
    {
        seed_systems();
    }

    /** @param  array<string, mixed>  $conditions */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $this->assertGreaterThan(
            0,
            $this->countWhere($table, $conditions),
            "esperava encontrar em {$table}: ".json_encode($conditions, JSON_UNESCAPED_UNICODE),
        );
    }

    /** @param  array<string, mixed>  $conditions */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $this->assertSame(
            0,
            $this->countWhere($table, $conditions),
            "não esperava encontrar em {$table}: ".json_encode($conditions, JSON_UNESCAPED_UNICODE),
        );
    }

    /** @param  array<string, mixed>  $conditions */
    private function countWhere(string $table, array $conditions): int
    {
        $where = [];
        $bindings = [];

        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = ?";
            $bindings[] = is_bool($value) ? (int) $value : $value;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM {$table} WHERE ".implode(' AND ', $where),
            $bindings,
        );
    }

    /** Aplica as migrations no banco em memória, uma vez para toda a suíte. */
    private function migrate(): void
    {
        $files = glob(__DIR__.'/../database/migrations/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            foreach ((require $file)['up'] as $sql) {
                Database::statement($sql);
            }
        }
    }
}
