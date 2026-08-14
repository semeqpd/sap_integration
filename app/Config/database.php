<?php

declare(strict_types=1);

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        // Conexão do middleware. Aceita as duas formas de configuração:
        //   - DB_URL=mysql://user:pass@host:3306/base?charset=utf8mb4  (ganha se preenchida)
        //   - DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
        // Quem faz o parse da URL e sobrepõe as chaves é App\Core\Database.
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'middleware'),
            'username' => env('DB_USERNAME', 'middleware'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'engine' => 'InnoDB',
            'ssl_ca' => env('MYSQL_ATTR_SSL_CA'),
        ],

        // Usada só pelos testes — banco em memória. Ver tests/bootstrap.php:
        // a troca para esta conexão é feita em código, não por variável de
        // ambiente.
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

    ],

];
