<?php

declare(strict_types=1);

// Tudo que era constante no topo do main.go em Go vive aqui: credenciais,
// de-paras fixos e o roteamento país -> filial. Nenhum código de serviço lê
// env() direto — só este arquivo.
return [

    // -----------------------------------------------------------------------
    // SAP Business One (Service Layer)
    // -----------------------------------------------------------------------
    'sap' => [
        'base_url' => rtrim((string) env('SAP_BASE_URL', 'https://localhost:50000/b1s/v1'), '/'),
        'company_db' => env('SAP_COMPANY_DB'),
        'username' => env('SAP_USERNAME'),
        'password' => env('SAP_PASSWORD'),

        // O Service Layer do lab usa certificado self-signed.
        'verify_tls' => (bool) env('SAP_VERIFY_TLS', false),
        'timeout' => (int) env('SAP_TIMEOUT', 30),

        // A sessão B1SESSION vale ~30min. Em PHP cada request é um processo
        // novo, então ela fica no cache compartilhado em vez da memória.
        'session_ttl_minutes' => (int) env('SAP_SESSION_TTL', 25),

        // De-paras ainda fixos (viram tabela/tela depois).
        'account_code' => env('SAP_ACCOUNT_CODE', '3.01.01.01.0001'),
        'item_code' => env('SAP_ITEM_CODE', '10'),
        'tax_code' => env('SAP_TAX_CODE', 'IVA01'),
        'branch_id' => (int) env('SAP_BRANCH_ID', 0),
    ],

    // -----------------------------------------------------------------------
    // Filial Filipinas — Jaz
    // -----------------------------------------------------------------------
    'jaz' => [
        'base_url' => rtrim((string) env('JAZ_BASE_URL', 'https://api.getjaz.com/api/v1'), '/'),
        'api_key' => env('JAZ_API_KEY'),
        'timeout' => (int) env('JAZ_TIMEOUT', 30),
        'page_size' => (int) env('JAZ_PAGE_SIZE', 100),
        'contacts_page_size' => (int) env('JAZ_CONTACTS_PAGE_SIZE', 200),
    ],

    // -----------------------------------------------------------------------
    // Filial EUA — Xero (Custom Connection, OAuth2 client_credentials)
    // -----------------------------------------------------------------------
    'xero' => [
        'token_url' => env('XERO_TOKEN_URL', 'https://identity.xero.com/connect/token'),
        'connections_url' => env('XERO_CONNECTIONS_URL', 'https://api.xero.com/connections'),
        'base_url' => rtrim((string) env('XERO_BASE_URL', 'https://api.xero.com/api.xro/2.0'), '/'),
        'client_id' => env('XERO_CLIENT_ID'),
        'client_secret' => env('XERO_CLIENT_SECRET'),
        'timeout' => (int) env('XERO_TIMEOUT', 30),
    ],

    // -----------------------------------------------------------------------
    // Cadastro vindo do SAP: país informado no webhook -> sistema da filial.
    // O rótulo exibido vem do nome em `systems`, não daqui.
    // -----------------------------------------------------------------------
    'branch_for_country' => [
        'php' => 'jaz_ph',
        'ph' => 'jaz_ph',
        'filipinas' => 'jaz_ph',
        'jaz_ph' => 'jaz_ph',
        'eua' => 'xero_us',
        'us' => 'xero_us',
        'usa' => 'xero_us',
        'xero_us' => 'xero_us',
    ],

    'default_branch' => env('DEFAULT_BRANCH', 'jaz_ph'),

    // -----------------------------------------------------------------------
    // Poll das filiais (no Go era um loop de 60s dentro do processo).
    // -----------------------------------------------------------------------
    'poll' => [
        // Trava distribuída: poll manual (tela) e agendado nunca se cruzam.
        'lock_seconds' => (int) env('POLL_LOCK_SECONDS', 300),
        'lock_wait_seconds' => (int) env('POLL_LOCK_WAIT_SECONDS', 10),
    ],

];
