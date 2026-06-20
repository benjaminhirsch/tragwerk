<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Integration;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Event\WebhookIntegrationDeleted;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectWebhookRepository $webhookRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');

        if ($activeProject instanceof Project) {
            $integration = $this->resolveIntegration($request, $activeProject);

            if ($integration instanceof ProjectWebhook) {
                $this->eventDispatcher->dispatch(
                    new WebhookIntegrationDeleted($integration->id, $activeProject->id),
                );
            }
        }

        return new RedirectResponse($this->urlHelper->generate('integration'));
    }

    private function resolveIntegration(ServerRequestInterface $request, Project $project): ProjectWebhook|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! WebhookIntegrationIdentifier::isValid($routeId)) {
            return null;
        }

        try {
            $integration = $this->webhookRepository->getById(WebhookIntegrationIdentifier::fromString($routeId));
        } catch (Throwable) {
            return null;
        }

        if ($integration->projectId->toString() !== $project->id->toString()) {
            return null;
        }

        return $integration;
    }
}
