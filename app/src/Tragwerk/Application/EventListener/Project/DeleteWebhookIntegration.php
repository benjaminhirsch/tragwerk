<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event\WebhookIntegrationDeleted;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;

final readonly class DeleteWebhookIntegration
{
    public function __construct(private ProjectWebhookRepository $repository)
    {
    }

    public function __invoke(WebhookIntegrationDeleted $event): void
    {
        $this->repository->delete($event->id);
    }
}
