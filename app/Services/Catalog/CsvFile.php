<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use RuntimeException;

/** Um CSV com cabeçalho, lido inteiro para memória (carga inicial é pequena). */
final readonly class CsvFile
{
    /** Caractere de escape do `fgetcsv` — ver o comentário em `read()`. */
    private const ESCAPE = '\\';

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, string>>  $rows
     */
    public function __construct(
        public string $path,
        public array $headers,
        public array $rows,
    ) {}

    public static function read(string $path, string $delimiter = ',', string $enclosure = '"'): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("arquivo CSV não encontrado: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("não consegui abrir o CSV: {$path}");
        }

        try {
            // O `escape` vai explícito: o PHP 8.4 avisa que o padrão vai mudar,
            // e "\\" é o padrão de hoje — passá-lo mantém a leitura idêntica
            // à do PHP_port em qualquer versão.
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure, self::ESCAPE);

            if ($headers === false || $headers === [null]) {
                throw new RuntimeException("CSV vazio (sem cabeçalho): {$path}");
            }

            // Excel/Postgres podem gravar BOM na primeira célula.
            $headers[0] = preg_replace('/^\x{FEFF}/u', '', (string) $headers[0]);
            $headers = array_map(static fn ($h): string => trim((string) $h), $headers);

            $rows = [];
            while (($linha = fgetcsv($handle, 0, $delimiter, $enclosure, self::ESCAPE)) !== false) {
                if ($linha === [null]) {
                    continue; // linha em branco
                }

                $registro = [];
                foreach ($headers as $i => $coluna) {
                    $registro[$coluna] = isset($linha[$i]) ? trim((string) $linha[$i]) : '';
                }
                $rows[] = $registro;
            }
        } finally {
            fclose($handle);
        }

        return new self($path, $headers, $rows);
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->headers, true);
    }

    /**
     * Indexa as linhas por uma coluna, para o cruzamento por chave.
     *
     * @return array<string, array<string, string>>
     */
    public function indexBy(string $column): array
    {
        $indexado = [];

        foreach ($this->rows as $linha) {
            $chave = $linha[$column] ?? '';
            if ($chave !== '') {
                $indexado[$chave] = $linha;
            }
        }

        return $indexado;
    }

    public function columnList(): string
    {
        return implode(', ', $this->headers);
    }
}
