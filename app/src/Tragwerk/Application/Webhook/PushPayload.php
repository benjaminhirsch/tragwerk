<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook;

final readonly class PushPayload
{
    public function __construct(
        public string $branch,
        public string $commitSha,
        public bool $deleted = false,
        // HTTPS clone URL of the source repo, when the forge payload carries it.
        // Lets the forge-webhook flow fetch the pushed commit into the bare repo.
        public string|null $cloneUrl = null,
    ) {
    }
}
