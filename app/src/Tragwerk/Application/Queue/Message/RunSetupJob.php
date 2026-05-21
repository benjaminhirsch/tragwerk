<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Message;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;

final readonly class RunSetupJob implements Message
{
    public function __construct(
        public SetupJobIdentifier $jobId,
    ) {
    }
}
