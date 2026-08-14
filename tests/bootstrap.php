<?php

declare(strict_types=1);

/**
 * Ambiente de teste — fixado aqui, em código, e não por variável de ambiente.
 *
 * Motivo (é o mesmo defeito que já mordeu o PHP_port): dentro do container o
 * compose injeta o `.env` como variável de ambiente real do processo, e nesse
 * caso configuração vinda do ambiente vence a do arquivo de teste — os testes
 * rodariam contra o MySQL de desenvolvimento e apagariam o banco. Fixando
 * abaixo, depois do bootstrap da aplicação, isso é impossível.
 */

use App\Core\Cache;
use App\Core\Config;
use App\Core\Lock;
use App\Core\Logger;

require __DIR__.'/../app/bootstrap.php';

// Banco em memória. A conexão PDO é uma só por processo (App\Core\Database),
// o que é obrigatório aqui: um SQLite `:memory:` morre junto com a conexão.
Config::set('database.default', 'sqlite');
Config::set('database.connections.sqlite.database', ':memory:');
Config::set('app.debug', true);
Config::set('app.env', 'testing');

// Cache, travas e log em pasta temporária: a suíte não encosta em storage/.
$temp = sys_get_temp_dir().'/middleware-tests-'.getmypid();

@mkdir($temp.'/cache', 0777, recursive: true);
@mkdir($temp.'/locks', 0777, recursive: true);

Cache::useDirectory($temp.'/cache');
Lock::useDirectory($temp.'/locks');
Logger::useFile($temp.'/app.log');

// A tabela de rotas, para os testes de Feature despacharem requisições.
require __DIR__.'/../routes/web.php';
require __DIR__.'/../routes/api.php';
require __DIR__.'/../routes/webhooks.php';

// Os seeders são funções, não classes — os testes chamam as que precisarem.
require_once __DIR__.'/../database/seeders/seed_systems.php';
require_once __DIR__.'/../database/seeders/seed_exchange_rates.php';
require_once __DIR__.'/../database/seeders/seed_demo.php';

register_shutdown_function(static function () use ($temp): void {
    foreach (glob($temp.'/{cache,locks}/*', GLOB_BRACE) ?: [] as $file) {
        @unlink($file);
    }
});
