<?php

declare(strict_types=1);

namespace Tragwerk\Application\Exception\TwoFactor;

use RuntimeException;

final class TwoFactorSecretDecryptionFailed extends RuntimeException
{
    public static function create(): self
    {
        return new self('Unable to decrypt the stored two-factor secret.');
    }

    public static function invalidKey(): self
    {
        return new self('The configured two-factor encryption key is missing or malformed.');
    }
}
