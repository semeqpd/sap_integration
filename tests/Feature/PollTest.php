<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Core\Container;
use App\Enums\InvoiceStatus;
use App\Integrations\BranchRegistry;
use App\Integrations\Dto\IncomingInvoice;
use App\Integrations\Dto\IncomingLine;
use App\Integrations\Sap\SapClient;
use App\Models\StagedInvoice;
use App\Services\InvoicePoller;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakeBranchClient;
use Tests\Support\FakeHttp;
use Tests\TestCase;

class PollTest extends TestCase
{
    private const PACIFIC = '02c73c1d-a22b-4a22-bdd5-c9ec8476b120';

    private FakeHttp $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSystems();
        seed_exchange_rates();
        seed_demo();

        $this->http = new FakeHttp(static fn (RequestInterface $request) => match (true) {
            str_contains((string) $request->getUri(), '/Login') => FakeHttp::json(['SessionId' => 'sessao-de-teste']),
            str_contains((string) $request->getUri(), '/Invoices') => FakeHttp::json(['DocEntry' => 999]),
            default => FakeHttp::json([], 404),
        });

        Container::instance(SapClient::class, new SapClient(
            Config::get('integrations.sap'),
            $this->http->handler(),
        ));
    }

    /** Substitui as filiais reais por uma fake, sem tocar nos serviços. */
    private function useBranch(FakeBranchClient $client): void
    {
        Container::instance(BranchRegistry::class, new BranchRegistry([$client]));
        // O poller já pode ter sido construído com o registry antigo.
        Container::forget(InvoicePoller::class);
    }

    private function poller(): InvoicePoller
    {
        /** @var InvoicePoller $poller */
        $poller = Container::get(InvoicePoller::class);

        return $poller;
    }

    public function test_primeiro_poll_registra_tudo_como_baseline(): void
    {
        $this->useBranch(new FakeBranchClient('jaz_ph', [$this->invoice('inv-1'), $this->invoice('inv-2')]));

        $result = $this->poller()->pollAll();

        $this->assertSame(2, $result->new);
        $this->assertSame(2, StagedInvoice::count('status = ?', [InvoiceStatus::Ignored->value]));
        $this->http->assertNothingSent(); // baseline não lança nada no SAP
    }

    public function test_invoice_nova_depois_do_baseline_e_processada(): void
    {
        $this->useBranch(new FakeBranchClient('jaz_ph', [$this->invoice('inv-1')]));
        $this->poller()->pollAll(); // baseline

        $this->useBranch(new FakeBranchClient('jaz_ph', [$this->invoice('inv-1'), $this->invoice('inv-2')]));
        $result = $this->poller()->pollAll();

        $this->assertSame(1, $result->new);
        $this->assertSame(InvoiceStatus::Posted, StagedInvoice::findByExternalCode('inv-2')->status);
        $this->assertSame(InvoiceStatus::Ignored, StagedInvoice::findByExternalCode('inv-1')->status);
    }

    public function test_poll_sem_invoice_nova_nao_muda_nada(): void
    {
        $this->useBranch(new FakeBranchClient('jaz_ph', [$this->invoice('inv-1')]));
        $this->poller()->pollAll();

        $result = $this->poller()->pollAll();

        $this->assertSame(0, $result->new);
        $this->assertSame(1, StagedInvoice::count());
    }

    public function test_falha_na_api_da_filial_vira_passo_e_nao_derruba_o_ciclo(): void
    {
        $this->useBranch(new FakeBranchClient('jaz_ph', failWith: 'Jaz retornou 401: chave inválida'));

        $result = $this->poller()->pollAll();

        $this->assertSame(0, $result->new);
        $this->assertStringContainsString('GET invoices falhou', $result->steps->toArray()[0]['desc']);
    }

    public function test_endpoint_de_poll_responde_a_tela(): void
    {
        $this->useBranch(new FakeBranchClient('jaz_ph', [$this->invoice('inv-1')]));

        $this->postJson('/api/poll')
            ->assertOk()
            ->assertJsonPath('new', 1)
            ->assertJsonStructure(['new', 'steps']);
    }

    private function invoice(string $code): IncomingInvoice
    {
        return new IncomingInvoice(
            systemId: 'jaz_ph',
            externalCode: $code,
            reference: strtoupper($code),
            contactCode: self::PACIFIC,
            contactName: 'Pacific Trade Co.',
            currency: 'PHP',
            total: 1000.0,
            documentDate: '2026-07-30',
            dueDate: '2026-08-30',
            notes: '',
            lines: [new IncomingLine('Rice', 10, 100)],
            raw: ['reference' => strtoupper($code)],
        );
    }
}
