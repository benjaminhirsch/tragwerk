<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

final readonly class WebhookIntegrationDeleted
{
    public function __construct(
        public WebhookIntegrationIdentifier $id,
        public ProjectIdentifier $projectId,
    ) {
    }
}
