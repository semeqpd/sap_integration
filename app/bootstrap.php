<?php

declare(strict_types=1);

/**
 * Subida da aplicação — o único lugar que monta tudo.
 *
 * Todo ponto de entrada passa por aqui: o front controller (`public/index.php`),
 * os scripts de `automations/`, `database/migrate.php`, os seeders e os testes.
 * Depois deste arquivo, a aplicação está pronta: `.env` lido, config carregada,
 * fuso definido e os serviços registrados no container.
 *
 * Este arquivo é o equivalente ao `AppServiceProvider::register()` do Laravel,
 * só que sem mágica: cada objeto é construído à mão, na ordem certa, recebendo
 * as dependências no construtor. Nenhuma classe de serviço lê `config()`/`env()`
 * por conta própria — tudo é injetado aqui.
 *
 * Acrescentar uma filial nova = escrever o client (implementando `BranchClient`)
 * e listá-lo no `BranchRegistry` abaixo. Nada de serviço, controller ou tela
 * precisa mudar.
 */

use App\Core\Config;
use App\Core\Container;
use App\Core\Env;
use App\Integrations\BranchRegistry;
use App\Integrations\Jaz\JazClient;
use App\Integrations\Sap\SapClient;
use App\Integrations\Sap\SapInvoicePayload;
use App\Integrations\Xero\XeroClient;
use App\Services\BranchRouter;
use App\Services\Catalog\CatalogImporter;
use App\Services\ContactCatalogSync;
use App\Services\CustomerRegistrar;
use App\Services\DemoInvoiceInjector;
use App\Services\InvoicePoller;
use App\Services\InvoiceProcessor;
use App\Services\LinkResolver;

// Rodar duas vezes (um script que inclui outro) não pode reconfigurar nada.
if (defined('APP_BASE_PATH')) {
    return;
}

define('APP_BASE_PATH', dirname(__DIR__));

require APP_BASE_PATH.'/vendor/autoload.php';
require APP_BASE_PATH.'/app/helpers.php';

Env::load(APP_BASE_PATH);
Config::load(APP_BASE_PATH.'/app/Config');

// Aplicação inteira em UTC; a tela é quem formata na exibição.
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

// ---------------------------------------------------------------------------
// SAP
// ---------------------------------------------------------------------------
Container::singleton(SapClient::class, static fn (): SapClient => new SapClient(
    Config::get('integrations.sap'),
));

Container::singleton(SapInvoicePayload::class, static fn (): SapInvoicePayload => new SapInvoicePayload(
    Config::get('integrations.sap'),
));

// ---------------------------------------------------------------------------
// Filiais
// ---------------------------------------------------------------------------
Container::singleton(JazClient::class, static fn (): JazClient => new JazClient(
    Config::get('integrations.jaz'),
));

Container::singleton(XeroClient::class, static fn (): XeroClient => new XeroClient(
    Config::get('integrations.xero'),
));

// Acrescentar uma filial nova = criar o client e listá-lo aqui.
Container::singleton(BranchRegistry::class, static fn (): BranchRegistry => new BranchRegistry([
    Container::get(JazClient::class),
    Container::get(XeroClient::class),
]));

// ---------------------------------------------------------------------------
// Serviços
// ---------------------------------------------------------------------------
Container::singleton(BranchRouter::class, static fn (): BranchRouter => new BranchRouter(
    Config::get('integrations.branch_for_country'),
    Config::get('integrations.default_branch'),
));

Container::singleton(CustomerRegistrar::class, static fn (): CustomerRegistrar => new CustomerRegistrar(
    Container::get(BranchRouter::class),
));

Container::singleton(LinkResolver::class, static fn (): LinkResolver => new LinkResolver);

Container::singleton(InvoiceProcessor::class, static fn (): InvoiceProcessor => new InvoiceProcessor(
    Container::get(SapClient::class),
    Container::get(SapInvoicePayload::class),
));

Container::singleton(InvoicePoller::class, static fn (): InvoicePoller => new InvoicePoller(
    Container::get(BranchRegistry::class),
    Container::get(InvoiceProcessor::class),
    Config::get('integrations.poll'),
));

Container::singleton(DemoInvoiceInjector::class, static fn (): DemoInvoiceInjector => new DemoInvoiceInjector(
    Container::get(InvoiceProcessor::class),
));

Container::singleton(ContactCatalogSync::class, static fn (): ContactCatalogSync => new ContactCatalogSync(
    Container::get(BranchRegistry::class),
));

Container::singleton(CatalogImporter::class, static fn (): CatalogImporter => new CatalogImporter);
