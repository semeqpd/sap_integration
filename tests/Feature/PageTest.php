<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PageTest extends TestCase
{
    public function test_a_pagina_carrega_com_as_tres_telas(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('MIDDLEWARE')
            ->assertSee('id="tab-links"')
            ->assertSee('id="tab-invoices"')
            ->assertSee('id="tab-db"')
            // CSS na ordem do design system + JS como módulo
            ->assertSee('css/base.css')
            ->assertSee('css/layout.css')
            ->assertSee('css/components.css')
            ->assertSee('type="module"');
    }

    public function test_tabela_fora_da_whitelist_nao_e_consultavel(): void
    {
        $this->seedSystems();

        $this->getJson('/api/tables/migrations')->assertNotFound();
        $this->getJson('/api/tables/entities')->assertOk()->assertJsonStructure(['columns', 'rows']);
    }

    public function test_contagem_das_tabelas_responde_o_ping_da_tela(): void
    {
        $this->seedSystems();

        $this->getJson('/api/tables')
            ->assertOk()
            ->assertJsonPath('systems', 5);
    }
}
