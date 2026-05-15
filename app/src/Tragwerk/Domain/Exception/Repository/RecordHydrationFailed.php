<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use RuntimeException;
use Throwable;

use function sprintf;

class RecordHydrationFailed extends RuntimeException implements RepositoryException
{
    /** @psalm-pure */
    public static function create(string $type, Throwable $previous): self
    {
        return new self(sprintf('Unable to hydrate record of type `%s`', $type), previous: $previous);
    }
}
