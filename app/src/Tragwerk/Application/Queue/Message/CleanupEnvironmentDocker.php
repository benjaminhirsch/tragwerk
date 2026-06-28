<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Message;

use Tragwerk\Application\Queue\Message;

final readonly class CleanupEnvironmentDocker implements Message
{
    public function __construct(
        public string $projectId,
        public string $branch,
        public string $host,
        public int $port,
        public string $credentialId,
    ) {
    }
}
