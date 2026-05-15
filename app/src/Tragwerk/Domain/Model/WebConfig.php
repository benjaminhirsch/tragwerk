<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class WebConfig
{
    /** @param list<LocationConfig> $locations */
    public function __construct(
        public array $locations,
    ) {
    }
}
