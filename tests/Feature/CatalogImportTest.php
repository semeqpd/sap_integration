<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Container;
use App\Models\ExternalRecord;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\CatalogManifest;
use InvalidArgumentException;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSystems();
        $this->fixtures = base_path('tests/Fixtures/catalog');
    }

    private function importer(): CatalogImporter
    {
        return Container::get(CatalogImporter::class);
    }

    private function manifest(): CatalogManifest
    {
        return CatalogManifest::load("{$this->fixtures}/config.json");
    }

    public function test_monta_codigo_e_nome_conforme_os_templates_do_manifesto(): void
    {
        [$relatorio] = $this->importer()->import($this->manifest());

        // 6 linhas em client.csv: 3 viram registro, 1 sem corporação, 1 sem id,
        // 1 (Itu/Heineken) entra porque o filtro `where` está vazio.
        $this->assertSame(4, $relatorio->inserted);
        $this->assertSame(1, $relatorio->skippedNoMatch); // corporação 99 não existe
        $this->assertSame(1, $relatorio->skippedBlank);   // linha sem id

        $this->assertDatabaseHas('external_records', [
            'system_id' => 'stream',
            'type' => 'customer',
            'external_code' => 'stream-10-101',
            'name' => 'Coca-Cola - Jundiaí',
        ]);

        $this->assertDatabaseHas('external_records', [
            'external_code' => 'stream-20-201',
            'name' => 'Ambev - Jaguariúna',
        ]);

        $this->assertSame(4, ExternalRecord::countFor('stream', 'customer'));
    }

    public function test_nao_sobrescreve_registro_que_ja_existe(): void
    {
        ExternalRecord::remember('stream', 'customer', 'stream-10-101', 'Nome definido na mão');

        [$relatorio] = $this->importer()->import($this->manifest());

        $this->assertSame(1, $relatorio->existing);
        $this->assertSame(3, $relatorio->inserted);

        $this->assertDatabaseHas('external_records', [
            'external_code' => 'stream-10-101',
            'name' => 'Nome definido na mão', // intacto
        ]);
    }

    public function test_only_if_empty_nao_reimporta_quando_o_catalogo_ja_tem_conteudo(): void
    {
        ExternalRecord::remember('stream', 'customer', 'qualquer-coisa', 'já existia');

        [$relatorio] = $this->importer()->import($this->manifest(), dryRun: false, onlyIfEmpty: true);

        $this->assertSame(0, $relatorio->inserted);
        $this->assertSame(1, ExternalRecord::countFor('stream', 'customer'));
    }

    public function test_dry_run_nao_grava_nada(): void
    {
        [$relatorio] = $this->importer()->import($this->manifest(), dryRun: true);

        $this->assertSame(4, $relatorio->inserted);
        $this->assertTrue($relatorio->dryRun);
        $this->assertNotEmpty($relatorio->samples);
        $this->assertSame(0, ExternalRecord::countFor('stream', 'customer'));
    }

    public function test_filtro_where_descarta_as_linhas_que_nao_batem(): void
    {
        $manifesto = $this->manifestoCom(['where' => ['corp.status' => ['active']]]);

        [$relatorio] = $this->importer()->import($manifesto);

        $this->assertSame(3, $relatorio->inserted);     // Heineken/Itu fica fora
        $this->assertSame(1, $relatorio->skippedFilter);
        $this->assertDatabaseMissing('external_records', ['external_code' => 'stream-30-301']);
    }

    public function test_coluna_inexistente_falha_dizendo_o_que_esta_disponivel(): void
    {
        $manifesto = $this->manifestoCom(['name' => '{corp.razao_social} - {client.name}']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('a coluna "razao_social"');

        $this->importer()->import($manifesto);
    }

    /**
     * Reescreve o manifesto de fixture com ajustes pontuais.
     *
     * @param  array<string, mixed>  $ajustes
     */
    private function manifestoCom(array $ajustes): CatalogManifest
    {
        $dados = json_decode((string) file_get_contents("{$this->fixtures}/config.json"), true);
        $dados['imports'][0] = array_merge($dados['imports'][0], $ajustes);

        $temporario = "{$this->fixtures}/config.temp.json";
        file_put_contents($temporario, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $manifesto = CatalogManifest::load($temporario);
        @unlink($temporario);

        return $manifesto;
    }
}
