<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Models\Entity;
use App\Models\Link;
use Tests\TestCase;

class SapCustomerWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSystems();
    }

    public function test_cadastro_novo_cria_entidade_e_abre_as_pendencias(): void
    {
        $response = $this->postJson('/webhook/sap/customer', [
            'CardCode' => 'C0104',
            'CardName' => 'Ambev Jaguariúna',
            'Country' => 'php',
        ]);

        $response->assertOk()
            ->assertJsonPath('entity.name', 'Ambev Jaguariúna')
            ->assertJsonCount(3, 'links');

        $entity = Entity::findByName('Ambev Jaguariúna');
        $this->assertNotNull($entity);

        // SAP fechado, Stream e a filial do país pendentes.
        $this->assertSame(LinkStatus::Linked, $entity->linkFor('sap')->status);
        $this->assertSame(LinkStatus::Pending, $entity->linkFor('stream')->status);
        $this->assertSame(LinkStatus::Pending, $entity->linkFor('jaz_ph')->status);

        $this->assertDatabaseHas('events', ['action' => 'sap_customer_webhook', 'success' => true]);
    }

    public function test_pais_eua_abre_a_pendencia_no_xero(): void
    {
        $this->postJson('/webhook/sap/customer', [
            'CardCode' => 'C0200',
            'CardName' => 'Liberty Foods',
            'Country' => 'eua',
        ])->assertOk();

        $entity = Entity::findByName('Liberty Foods');

        $this->assertNotNull($entity->linkFor('xero_us'));
        $this->assertNull($entity->linkFor('jaz_ph'));
    }

    public function test_cadastro_repetido_nao_duplica_entidade_nem_vinculo(): void
    {
        $payload = ['CardCode' => 'C0104', 'CardName' => 'Ambev Jaguariúna', 'Country' => 'php'];

        $this->postJson('/webhook/sap/customer', $payload)->assertOk();
        $this->postJson('/webhook/sap/customer', $payload)->assertOk();

        $this->assertSame(1, Entity::count());
        $this->assertSame(3, Link::count());
    }

    public function test_campos_obrigatorios_sao_validados(): void
    {
        $this->postJson('/webhook/sap/customer', ['CardName' => 'Sem código'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('CardCode');
    }
}
