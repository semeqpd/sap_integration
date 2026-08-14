<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * GET /up — o mesmo health check que o Laravel expunha.
 *
 * Não é usado pela tela: serve para monitoração e para conferir rapidamente,
 * de fora do container, se o Apache e o PHP estão de pé.
 */
final class HealthController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->json(['status' => 'ok']);
    }
}
