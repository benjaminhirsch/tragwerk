<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

final class ProjectWebhook implements Entity
{
    public function __construct(
        public WebhookIntegrationIdentifier $id,
        public ProjectIdentifier $projectId,
        public GitForge $forge,
        public string $secret,
        public TimestampImmutable $createdAt,
    ) {
    }
}
