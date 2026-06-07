<?php

declare(strict_types=1);

return [
    'mercure' => [
        'hub_url'              => $_SERVER['MERCURE_HUB_URL'] ?? 'http://localhost/.well-known/mercure',
        'publisher_jwt_secret' => $_SERVER['MERCURE_PUBLISHER_JWT_SECRET'] ?? '',
        'topic_base'           => $_SERVER['MERCURE_TOPIC_BASE'] ?? 'https://tragwerk.build',
    ],
];
