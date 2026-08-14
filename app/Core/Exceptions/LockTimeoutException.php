<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/** Não deu para pegar a trava dentro do tempo de espera. */
class LockTimeoutException extends RuntimeException {}
