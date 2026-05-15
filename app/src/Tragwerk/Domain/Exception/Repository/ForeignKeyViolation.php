<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use RuntimeException;
use Tragwerk\Domain\ValueObject\EntityIdentifier;

use function sprintf;

class ForeignKeyViolation extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function forUserProfile(EntityIdentifier $identifier): self
    {
        return new self(sprintf('Unable to create or update profile, no matching user found for `%s`', $identifier));
    }
}
