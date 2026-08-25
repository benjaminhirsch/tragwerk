<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use function in_array;
use function str_starts_with;

/**
 * Environment variable keys Tragwerk writes into every application container itself — platform
 * metadata, relationship connection details and the FrankenPHP settings. DockerComposeGenerator
 * writes them after the user-defined variables, so a user-defined variable of the same name would
 * silently have no effect; the variable forms reject those keys up front instead.
 */
final readonly class ReservedEnvVars
{
    private const string PREFIX = 'TRAGWERK_';

    private const array KEYS = ['TRAGWERK', 'SERVER_NAME', 'MAX_REQUESTS'];

    public static function isReserved(string $key): bool
    {
        return str_starts_with($key, self::PREFIX) || in_array($key, self::KEYS, true);
    }
}
