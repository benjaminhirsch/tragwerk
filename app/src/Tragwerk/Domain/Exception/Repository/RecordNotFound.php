<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use RuntimeException;

use function sprintf;

class RecordNotFound extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function fromField(string $fieldName, string $type, string $identifier): self
    {
        return new self(sprintf('Record of type %s with %s %s not found', $type, $fieldName, $identifier));
    }
}
