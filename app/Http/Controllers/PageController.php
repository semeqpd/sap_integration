<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** Única página da aplicação: as três telas vivem em abas dentro dela. */
final class PageController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->html(View::render('layout'));
    }
}
