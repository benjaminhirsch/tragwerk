<?php

declare(strict_types=1);

return [
    'database' => [
        'default' => [
            'database' => $postgresCredentials['path'] ?? null,
            'host' => $postgresCredentials['host'] ?? null,
            'port' => $postgresCredentials['port'] ?? null,
            'username' => $postgresCredentials['username'] ?? null,
            'password' => $postgresCredentials['password'] ?? null,
        ],
    ],
];
