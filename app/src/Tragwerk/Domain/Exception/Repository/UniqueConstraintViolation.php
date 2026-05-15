<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use RuntimeException;

use function sprintf;

class UniqueConstraintViolation extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function forUser(string $email): self
    {
        return new self(sprintf('Duplicate entry found for `%s`', $email));
    }
}
