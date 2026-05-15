<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class MissingConfiguration extends RuntimeException
{
    public static function createFromSubject(
        string $subject,
        int $code = 0,
        Throwable|null $previous = null,
    ): self {
        return new self(sprintf('Missing %s in configuration', $subject), $code, $previous);
    }
}
