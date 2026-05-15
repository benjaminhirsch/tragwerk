<?php

declare(strict_types=1);

use Psr\Log\LogLevel;

return [
    'loggers' => [
        'app' => [
            'level'        => getenv('LOG_LEVEL') ?: LogLevel::ERROR,
            'infoConsole'  => false,
            'errorConsole' => false,
            'infoFile'     => null,
            'errorFile'    => 'data/logging/app.log',
        ],
    ],
];
