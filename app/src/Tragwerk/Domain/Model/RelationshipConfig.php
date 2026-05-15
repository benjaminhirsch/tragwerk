<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class RelationshipConfig
{
    public function __construct(
        public string $name,
        public string $target,
    ) {
    }
}
