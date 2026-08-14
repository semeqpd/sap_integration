#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Ciclo de poll das filiais — o mesmo que o botão "Verificar agora" da tela
 * dispara.
 *
 *     php automations/poll.php
 *
 * Cadência: a cada minuto, pelo cron (ver docker/cron/crontab).
 *
 * Dois ciclos nunca rodam ao mesmo tempo: o `InvoicePoller` pega uma trava em
 * arquivo antes de começar, e ela vale entre processos — não importa se o
 * disparo veio do cron ou da tela. Por isso não é preciso `flock` na linha do
 * crontab.
 */

use App\Core\Container;
use App\Services\InvoicePoller;
use App\Support\Flow\Step;

require __DIR__.'/../app/bootstrap.php';

/** @var InvoicePoller $poller */
$poller = Container::get(InvoicePoller::class);

$result = $poller->pollAll();

foreach ($result->steps->all() as $step) {
    echo format_step($step)."\n";
}

echo "{$result->new} invoice(s) nova(s)\n";

function format_step(Step $step): string
{
    $tag = $step->table !== null ? "[{$step->op?->value} {$step->table}] " : '';

    return "  {$tag}{$step->desc}";
}
