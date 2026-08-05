<?php

declare(strict_types=1);

$enabled = getenv('TRAGWERK_REGISTRATION_ENABLED');

return [
    'registration' => [
        // Public sign-up at /register. Disable it on private instances; accounts
        // are then created with `bin/cli user:create` or through a team
        // invitation, which keeps working either way.
        // Provide via the TRAGWERK_REGISTRATION_ENABLED environment variable
        // (false/0/no/off disable it). Unset or empty means enabled.
        'enabled' => $enabled === false || $enabled === ''
            ? true
            : filter_var($enabled, FILTER_VALIDATE_BOOL),
    ],
];
