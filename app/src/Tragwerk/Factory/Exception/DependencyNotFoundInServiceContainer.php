<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Exception;

use RuntimeException;

use function sprintf;

final class DependencyNotFoundInServiceContainer extends RuntimeException
{
    public static function create(string $dependency): self
    {
        return new self(sprintf('Container does not contain a registered service for `%s`', $dependency));
    }
}
