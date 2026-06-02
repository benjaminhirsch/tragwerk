<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Message;

use Tragwerk\Application\Queue\Message;

final readonly class PruneRegistryImages implements Message
{
    public function __construct(
        public string $registryId,
        public string $appSlug,
        public string $branchSlug,
    ) {
    }
}
