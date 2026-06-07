<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event\WebhookIntegrationCreated;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;

final readonly class CreateWebhookIntegration
{
    public function __construct(private ProjectWebhookRepository $repository)
    {
    }

    public function __invoke(WebhookIntegrationCreated $event): void
    {
        $this->repository->create($event->integration);
    }
}
