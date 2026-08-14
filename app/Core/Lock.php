<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\LockTimeoutException;

/**
 * Trava compartilhada entre processos, em arquivo.
 *
 * Substitui o `Cache::lock()` do Laravel e tem a mesma semântica:
 *
 *   - **exclusiva**: só um processo segura a trava por vez;
 *   - **com prazo (TTL)**: se quem segurava travou/morreu sem soltar, a trava
 *     expira sozinha e o próximo ciclo consegue rodar;
 *   - **solta por quem pegou**: `release()` só apaga a trava se o dono for este
 *     processo.
 *
 * O `flock()` aqui serve para o pedaço curto de ler-e-gravar o arquivo de
 * trava, não para segurar a trava em si — segurar via `flock` deixaria um
 * processo pendurado bloqueando o poll para sempre, que é justamente o que o
 * TTL evita.
 *
 * Quem usa: `InvoicePoller` (um ciclo de poll por vez, venha ele do cron ou do
 * botão da tela).
 */
final class Lock
{
    private static ?string $directory = null;

    /** Identifica este processo como dono da trava. */
    private readonly string $owner;

    public function __construct(private readonly string $key)
    {
        $this->owner = bin2hex(random_bytes(16));
    }

    /** Troca a pasta das travas (os testes usam uma temporária). */
    public static function useDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    /** Tenta pegar a trava agora. `false` = outro processo está com ela. */
    public function acquire(int $ttlSeconds): bool
    {
        $handle = $this->open();

        if ($handle === false) {
            return false;
        }

        try {
            flock($handle, LOCK_EX);

            $record = json_decode((string) stream_get_contents($handle), true);
            $taken = is_array($record)
                && isset($record['expires_at'])
                && $record['expires_at'] > time();

            if ($taken) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode([
                'owner' => $this->owner,
                'expires_at' => time() + $ttlSeconds,
            ]));
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Espera até `$waitSeconds` pela trava.
     *
     * @throws LockTimeoutException quando o tempo acaba e outro processo ainda a segura
     */
    public function block(int $waitSeconds, int $ttlSeconds): void
    {
        $deadline = microtime(true) + $waitSeconds;

        do {
            if ($this->acquire($ttlSeconds)) {
                return;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        throw new LockTimeoutException("não consegui a trava \"{$this->key}\" em {$waitSeconds}s");
    }

    /** Solta a trava — só se este processo ainda for o dono dela. */
    public function release(): void
    {
        $handle = $this->open();

        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);

            $record = json_decode((string) stream_get_contents($handle), true);

            if (is_array($record) && ($record['owner'] ?? null) === $this->owner) {
                ftruncate($handle, 0);
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource|false */
    private function open()
    {
        $directory = self::$directory ??= storage_path('locks');

        if (! is_dir($directory)) {
            @mkdir($directory, 0777, recursive: true);
        }

        // 'c+' abre para leitura/escrita e cria se não existir, sem truncar.
        return @fopen($directory.'/'.sha1($this->key).'.lock', 'c+');
    }
}
