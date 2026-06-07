<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\ProjectWebhook;

final readonly class WebhookIntegrationCreated
{
    public function __construct(public ProjectWebhook $integration)
    {
    }
}
