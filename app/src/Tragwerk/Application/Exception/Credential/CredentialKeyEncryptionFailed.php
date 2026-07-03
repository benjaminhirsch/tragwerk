<?php

declare(strict_types=1);

namespace Tragwerk\Application\Exception\Credential;

use RuntimeException;

final class CredentialKeyEncryptionFailed extends RuntimeException
{
    public static function create(): self
    {
        return new self('Unable to decrypt the stored SSH private key.');
    }

    public static function invalidKey(): self
    {
        return new self('The configured credential encryption key is missing or malformed.');
    }
}
