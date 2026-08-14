#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Roda todos os seeders, na ordem das dependências.
 *
 *     php database/seed.php
 *     php database/migrate.php --seed     (mesma coisa, depois de migrar)
 *
 * Todos são idempotentes: o entrypoint do container roda isto a cada subida e
 * nada pode duplicar. Para rodar um só, chame o arquivo direto:
 *
 *     php database/seeders/seed_systems.php
 */

require_once __DIR__.'/../app/bootstrap.php';

require_once __DIR__.'/seeders/seed_systems.php';
require_once __DIR__.'/seeders/seed_exchange_rates.php';
require_once __DIR__.'/seeders/seed_catalog.php';
require_once __DIR__.'/seeders/seed_demo.php';

echo '  systems: '.seed_systems()." sistema(s) garantido(s).\n";
echo '  exchange_rates: '.seed_exchange_rates()." taxa(s) garantida(s).\n";

// Carga inicial vinda dos CSVs de database/init (só o que faltar).
seed_catalog();

seed_demo();
echo "  demo: entidades, vínculos e catálogo de demonstração garantidos.\n";
