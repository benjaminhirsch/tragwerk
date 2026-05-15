<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use Override;
use Throwable;

use function sprintf;

final class EntityHydrationFailed extends RecordHydrationFailed
{
    /** @psalm-pure */
    #[Override]
    public static function create(string $type, Throwable $previous): self
    {
        return new self(sprintf('Unable to hydrate entity of type `%s`', $type), previous: $previous);
    }
}
