<?php

declare(strict_types=1);

$encryptionKey = getenv('CREDENTIAL_ENCRYPTION_KEY');

return [
    'credential' => [
        // Base64-encoded 32-byte key for sodium secretbox encryption of SSH
        // private keys at rest. Provide via the CREDENTIAL_ENCRYPTION_KEY
        // environment variable. It must live outside the database so a leaked
        // credentials table stays unusable.
        // Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
        'encryption_key' => $encryptionKey !== false ? $encryptionKey : null,
    ],
];
