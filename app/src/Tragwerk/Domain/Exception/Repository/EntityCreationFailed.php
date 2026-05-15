<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use Tragwerk\Domain\ValueObject\EntityIdentifier;
use RuntimeException;
use Throwable;

use function sprintf;

final class EntityCreationFailed extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function create(string $type, EntityIdentifier $identifier, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Unable to create entity of type `%s` with id `%s`: %s',
                $type,
                $identifier,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }
}
