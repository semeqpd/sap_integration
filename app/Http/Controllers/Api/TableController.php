<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Database\BrowsableTables;
use DateTimeInterface;

/** Tela "Banco": contagem e conteúdo bruto das tabelas da whitelist. */
final class TableController extends Controller
{
    private const ROW_LIMIT = 100;

    /** GET /api/tables */
    public function counts(Request $request): Response
    {
        $counts = [];

        foreach (BrowsableTables::all() as $table) {
            $counts[$table] = (int) Database::scalar("SELECT COUNT(*) FROM {$table}");
        }

        return $this->json($counts);
    }

    /** GET /api/tables/{name} — últimas linhas, tudo como texto. */
    public function rows(Request $request, string $name): Response
    {
        // O nome da tabela entra na query, então só passa pela whitelist.
        $table = BrowsableTables::assert($name);
        $columns = Database::columnListing($table);

        $rows = Database::select(sprintf(
            'SELECT * FROM %s ORDER BY %s DESC LIMIT %d',
            $table,
            $columns[0],   // primeira coluna = chave/ordem natural
            self::ROW_LIMIT,
        ));

        return $this->json([
            'columns' => $columns,
            'rows' => array_map(
                fn (array $row): array => array_map(
                    fn (string $column): string => $this->stringify($row[$column] ?? null),
                    $columns,
                ),
                $rows,
            ),
        ]);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
