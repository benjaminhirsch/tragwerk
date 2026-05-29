<?php

declare(strict_types=1);

$dbHost     = getenv('TRAGWERK_DATABASE_HOST') ?: null;
$dbPort     = getenv('TRAGWERK_DATABASE_PORT') ?: null;
$dbDatabase = getenv('TRAGWERK_DATABASE_DATABASE') ?: null;
$dbUser     = getenv('TRAGWERK_DATABASE_USER') ?: null;
$dbPassword = getenv('TRAGWERK_DATABASE_PASSWORD') ?: null;

return [
    'database' => [
        'default' => [
            'database' => $dbDatabase,
            'host'     => $dbHost,
            'port'     => $dbPort !== null ? (int) $dbPort : null,
            'username' => $dbUser,
            'password' => $dbPassword,
        ],
    ],
];
