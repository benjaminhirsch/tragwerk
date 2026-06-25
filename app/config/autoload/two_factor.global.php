<?php

declare(strict_types=1);

$encryptionKey = getenv('TWO_FACTOR_KEY');
$trustedDays   = getenv('TWO_FACTOR_TRUSTED_DEVICE_DAYS');

return [
    'two_factor' => [
        // Base64-encoded 32-byte key for sodium secretbox encryption of TOTP
        // secrets at rest. Provide via the TWO_FACTOR_KEY environment variable.
        // Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
        'encryption_key'      => $encryptionKey !== false ? $encryptionKey : null,
        'issuer'              => 'Tragwerk',
        'trusted_device_days' => is_numeric($trustedDays) ? (int) $trustedDays : 30,
    ],
];
