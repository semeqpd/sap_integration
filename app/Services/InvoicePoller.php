<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Exceptions\LockTimeoutException;
use App\Core\Lock;
use App\Core\Logger;
use App\Enums\EventDirection;
use App\Integrations\BranchRegistry;
use App\Integrations\Contracts\BranchClient;
use App\Integrations\IntegrationException;
use App\Models\Event;
use App\Models\StagedInvoice;
use App\Models\System;
use App\Services\Results\PollResult;
use App\Support\Flow\StepLog;

/**
 * Ciclo de consulta às filiais.
 *
 * No middleware em Go isto era uma goroutine com mutex; aqui os disparos vêm
 * do cron e do botão da tela, em processos diferentes — a exclusão mútua
 * passa a ser uma trava em arquivo, que vale para todos eles.
 */
final readonly class InvoicePoller
{
    private const LOCK_KEY = 'integration:poll';

    /** @param  array<string, mixed>  $config  Config::get('integrations.poll') */
    public function __construct(
        private BranchRegistry $branches,
        private InvoiceProcessor $processor,
        private array $config,
    ) {}

    /** Roda um ciclo em todas as filiais registradas. */
    public function pollAll(): PollResult
    {
        $lock = new Lock(self::LOCK_KEY);

        try {
            $lock->block((int) $this->config['lock_wait_seconds'], (int) $this->config['lock_seconds']);
        } catch (LockTimeoutException) {
            Logger::info('[poll] outro ciclo em andamento — ignorando este disparo');

            return new PollResult(0, StepLog::make()->note('Poll já em execução — este disparo foi ignorado'));
        }

        try {
            $steps = StepLog::make();
            $new = 0;

            foreach ($this->branches->all() as $client) {
                $result = $this->pollBranch($client);
                $new += $result->new;
                $steps->merge($result->steps);
            }

            return new PollResult($new, $steps);
        } finally {
            $lock->release();
        }
    }

    /**
     * Um ciclo de uma filial. Se o staging dela está vazio é a primeira vez:
     * tudo entra como baseline (ignored) e daí em diante só o que for novo
     * é processado.
     */
    private function pollBranch(BranchClient $client): PollResult
    {
        $steps = StepLog::make();
        $systemId = $client->systemId();
        $label = System::labelFor($systemId);

        try {
            $invoices = $client->invoices();
        } catch (IntegrationException $e) {
            $this->logErrorOnce($systemId, $e->getMessage());
            $steps->api($label, 'GET invoices falhou: '.$e->getMessage());

            return new PollResult(0, $steps);
        }

        $this->clearError($systemId);
        $steps->api($label, sprintf('GET invoices -> %d invoices', count($invoices)));

        $baseline = ! StagedInvoice::hasAnyFor($systemId);
        $new = 0;

        foreach ($invoices as $invoice) {
            if (StagedInvoice::seen($systemId, $invoice->externalCode)) {
                continue;
            }

            $new++;

            if ($baseline) {
                $this->processor->registerBaseline($invoice);

                continue;
            }

            $steps->merge($this->processor->process($invoice));
        }

        if ($baseline) {
            $steps->insert('invoice_staging', "baseline {$label}: {$new} invoices existentes registradas como ignored");
            Logger::info("[{$systemId}] baseline: {$new} invoices existentes registradas; daqui em diante só novas");
            Event::record($systemId, EventDirection::Inbound, 'invoice_baseline', true, ['count' => $new]);
        } elseif ($new > 0) {
            Logger::info("[{$systemId}] poll: {$new} invoice(s) nova(s)");
        } else {
            $steps->select('invoice_staging', "{$label}: nenhuma invoice nova (todas já vistas)");
        }

        return new PollResult($new, $steps);
    }

    /**
     * Só loga quando a mensagem muda: um erro de configuração se repetiria a
     * cada ciclo e afogaria o log. A tela continua mostrando o passo.
     */
    private function logErrorOnce(string $systemId, string $message): void
    {
        $key = "poll:last-error:{$systemId}";

        if (Cache::get($key) === $message) {
            return;
        }

        Logger::warning("[{$systemId}] erro no poll: {$message} (silenciando repetições)");
        Cache::put($key, $message, 24 * 3600);
    }

    private function clearError(string $systemId): void
    {
        $key = "poll:last-error:{$systemId}";

        if (Cache::has($key)) {
            Logger::info("[{$systemId}] poll normalizado");
            Cache::forget($key);
        }
    }
}
