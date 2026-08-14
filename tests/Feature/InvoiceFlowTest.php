<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Core\Container;
use App\Enums\InvoiceStatus;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Integrations\Sap\SapClient;
use App\Models\StagedInvoice;
use App\Services\InvoiceProcessor;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakeHttp;
use Tests\TestCase;

class InvoiceFlowTest extends TestCase
{
    private FakeHttp $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSystems();
        seed_exchange_rates();
        seed_demo(); // Pacific Trade já vinculado sap <-> jaz

        Config::set('integrations.sap.base_url', 'https://sap.test/b1s/v1');

        // Troca a rede por respostas de mentira: nenhum serviço sabe disso.
        $this->http = new FakeHttp(static fn (RequestInterface $request) => match (true) {
            str_contains((string) $request->getUri(), '/Login') => FakeHttp::json(['SessionId' => 'sessao-de-teste']),
            str_contains((string) $request->getUri(), '/Invoices') => FakeHttp::json(['DocEntry' => 4321]),
            default => FakeHttp::json(['erro' => 'endpoint inesperado'], 404),
        });

        Container::instance(SapClient::class, new SapClient(
            Config::get('integrations.sap'),
            $this->http->handler(),
        ));
    }

    public function test_invoice_de_demonstracao_e_lancada_no_sap(): void
    {
        $this->postJson('/api/invoices/demo')
            ->assertOk()
            ->assertJsonStructure(['reference', 'steps']);

        $invoice = StagedInvoice::latest();

        $this->assertSame(InvoiceStatus::Posted, $invoice->status);
        $this->assertSame(4321, $invoice->sap_doc_entry);
        $this->assertSame('PHP', $invoice->currency);
        // 10 x 85 + 5 x 120 = 1450 PHP; taxa do seed = 0,095 -> R$ 137,75
        $this->assertEquals(1450.0, (float) $invoice->source_amount);
        $this->assertEquals(137.75, (float) $invoice->amount_brl);

        $this->assertDatabaseHas('events', ['action' => 'sap_invoice_post', 'success' => true]);
    }

    public function test_invoice_de_cliente_sem_vinculo_fica_bloqueada_e_nao_chama_o_sap(): void
    {
        $steps = $this->processor()->process($this->invoiceFrom('contato-desconhecido'));

        $invoice = StagedInvoice::latest();

        $this->assertSame(InvoiceStatus::Blocked, $invoice->status);
        $this->assertStringContainsString('sem vínculo no SAP', (string) $invoice->block_reason);
        $this->assertNull($invoice->sap_doc_entry);

        $this->http->assertNothingSent();
        $this->assertNotEmpty($steps->toArray());
    }

    public function test_moeda_sem_taxa_cadastrada_bloqueia_a_invoice(): void
    {
        $this->processor()->process(
            $this->invoiceFrom('02c73c1d-a22b-4a22-bdd5-c9ec8476b120', currency: 'JPY'),
        );

        $invoice = StagedInvoice::latest();

        $this->assertSame(InvoiceStatus::Blocked, $invoice->status);
        $this->assertStringContainsString('sem taxa cadastrada para JPY', (string) $invoice->block_reason);
    }

    public function test_a_mesma_invoice_nunca_entra_duas_vezes_no_staging(): void
    {
        $invoice = $this->invoiceFrom('02c73c1d-a22b-4a22-bdd5-c9ec8476b120');

        $this->processor()->process($invoice);
        $this->processor()->process($invoice);

        $this->assertSame(1, StagedInvoice::count('external_code = ?', [$invoice->externalCode]));
    }

    private function processor(): InvoiceProcessor
    {
        /** @var InvoiceProcessor $processor */
        $processor = Container::get(InvoiceProcessor::class);

        return $processor;
    }

    private function invoiceFrom(string $contactCode, string $currency = 'PHP'): IncomingInvoice
    {
        return new IncomingInvoice(
            systemId: 'jaz_ph',
            externalCode: 'inv-teste-1',
            reference: 'INV-TESTE-1',
            contactCode: $contactCode,
            contactName: 'Cliente de Teste',
            currency: $currency,
            total: 200.0,
            documentDate: '2026-07-30',
            dueDate: '2026-08-30',
            notes: '',
            lines: [new IncomingLine('Serviço', 2, 100)],
            raw: ['reference' => 'INV-TESTE-1'],
        );
    }
}
