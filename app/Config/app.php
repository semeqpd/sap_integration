<?php

declare(strict_types=1);

return [

    'name' => env('APP_NAME', 'SEMEQ Middleware'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    // Tudo em UTC no banco; a tela formata na exibição.
    'timezone' => env('APP_TIMEZONE', 'UTC'),

];
