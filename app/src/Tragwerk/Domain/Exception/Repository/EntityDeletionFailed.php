<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use RuntimeException;
use Throwable;
use Tragwerk\Domain\ValueObject\EntityIdentifier;

use function sprintf;

final class EntityDeletionFailed extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function create(EntityIdentifier $identifier, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Unable to delete entity of type `%s` with id `%s`: %s',
                $identifier::getEntityType()->value,
                $identifier,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }
}
