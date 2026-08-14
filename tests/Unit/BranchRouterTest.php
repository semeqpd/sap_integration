<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BranchRouter;
use PHPUnit\Framework\TestCase;

class BranchRouterTest extends TestCase
{
    private function router(): BranchRouter
    {
        return new BranchRouter(['php' => 'jaz_ph', 'eua' => 'xero_us', 'us' => 'xero_us'], 'jaz_ph');
    }

    public function test_mapeia_pais_para_a_filial(): void
    {
        $this->assertSame('xero_us', $this->router()->forCountry('eua'));
        $this->assertSame('jaz_ph', $this->router()->forCountry('php'));
    }

    public function test_ignora_caixa_e_espacos(): void
    {
        $this->assertSame('xero_us', $this->router()->forCountry('  EUA '));
    }

    public function test_cai_no_padrao_quando_o_pais_e_desconhecido_ou_vazio(): void
    {
        $this->assertSame('jaz_ph', $this->router()->forCountry('narnia'));
        $this->assertSame('jaz_ph', $this->router()->forCountry(null));
    }
}
