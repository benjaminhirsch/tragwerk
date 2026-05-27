<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\SshKey;

final readonly class SshKeyCreated
{
    public function __construct(
        public SshKey $key,
    ) {
    }
}
