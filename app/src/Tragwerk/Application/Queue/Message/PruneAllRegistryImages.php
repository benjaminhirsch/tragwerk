<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Message;

use Tragwerk\Application\Queue\Message;

final readonly class PruneAllRegistryImages implements Message
{
    /** @param list<string> $prefixes */
    public function __construct(
        public string $registryId,
        public array $prefixes,
    ) {
    }
}
