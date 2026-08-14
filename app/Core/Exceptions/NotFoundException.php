<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/** Recurso inexistente — vira 404 no front controller. */
class NotFoundException extends RuntimeException {}
