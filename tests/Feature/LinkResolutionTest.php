<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntityType;
use App\Enums\LinkStatus;
use App\Models\Entity;
use App\Models\ExternalRecord;
use App\Models\Link;
use Tests\TestCase;

class LinkResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSystems();
    }

    private function pendingLink(): Link
    {
        $entity = Entity::create([
            'type' => EntityType::Customer,
            'name' => 'Coca-Cola Jundiaí',
            'created_from' => 'sap',
        ]);

        return $entity->createLink([
            'entity_type' => EntityType::Customer->value,
            'system_id' => 'stream',
            'status' => LinkStatus::Pending,
            'source' => 'auto',
        ]);
    }

    public function test_vincula_a_um_registro_do_catalogo(): void
    {
        $link = $this->pendingLink();
        ExternalRecord::remember('stream', 'customer', '001', 'coca cola-jundiai');

        $this->postJson("/api/links/{$link->id}/resolve", [
            'external_code' => '001',
            'external_name' => 'coca cola-jundiai',
            'linked_by' => 'gustavo@semeq.com',
        ])->assertOk()->assertJsonPath('entity.name', 'Coca-Cola Jundiaí');

        $link->refresh();
        $this->assertSame(LinkStatus::Linked, $link->status);
        $this->assertSame('001', $link->external_code);
        $this->assertSame('gustavo@semeq.com', $link->linked_by);
        $this->assertNotNull($link->last_synced_at);
    }

    public function test_adicionar_novo_cria_registro_no_catalogo_e_vincula(): void
    {
        $link = $this->pendingLink();

        $this->postJson("/api/links/{$link->id}/resolve", [
            'create_new' => true,
            'external_name' => 'planta nova',
        ])->assertOk();

        $link->refresh();
        $this->assertSame(LinkStatus::Linked, $link->status);
        $this->assertStringStartsWith('NEW-', (string) $link->external_code);

        $this->assertDatabaseHas('external_records', [
            'system_id' => 'stream',
            'external_code' => $link->external_code,
            'name' => 'planta nova',
        ]);
    }

    public function test_sem_codigo_e_sem_novo_registro_a_requisicao_e_rejeitada(): void
    {
        $link = $this->pendingLink();

        $this->postJson("/api/links/{$link->id}/resolve", ['linked_by' => 'tela'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('external_code');

        $this->assertSame(LinkStatus::Pending, $link->refresh()->status);
    }

    public function test_a_pendencia_sai_da_fila_depois_de_resolvida(): void
    {
        $link = $this->pendingLink();
        ExternalRecord::remember('stream', 'customer', '001', 'coca cola-jundiai');

        $this->getJson('/api/pending')->assertOk()->assertJsonCount(1);

        $this->postJson("/api/links/{$link->id}/resolve", ['external_code' => '001'])->assertOk();

        $this->getJson('/api/pending')->assertOk()->assertJsonCount(0);
    }
}
