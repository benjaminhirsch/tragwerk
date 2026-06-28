<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook;

final readonly class PushPayload
{
    public function __construct(
        public string $branch,
        public string $commitSha,
        public bool $deleted = false,
    ) {
    }
}
