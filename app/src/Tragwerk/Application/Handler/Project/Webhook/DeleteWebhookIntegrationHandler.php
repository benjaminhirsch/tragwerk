<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project\Webhook;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Event\WebhookIntegrationDeleted;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

use function assert;
use function in_array;
use function is_string;

final readonly class DeleteWebhookIntegrationHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ProjectWebhookRepository $webhookRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $webhookId = $request->getAttribute('webhookId');

        if (! is_string($webhookId) || ! WebhookIntegrationIdentifier::isValid($webhookId)) {
            return new EmptyResponse(400);
        }

        try {
            $integration = $this->webhookRepository->getById(
                WebhookIntegrationIdentifier::fromString($webhookId),
            );
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        if ($integration->projectId->toString() !== $project->id->toString()) {
            return new EmptyResponse(403);
        }

        $this->eventDispatcher->dispatch(new WebhookIntegrationDeleted($integration->id, $project->id));

        return $this->renderList($request, $project);
    }

    private function renderList(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        $integrations = $this->webhookRepository->findByProject($project->id);
        $usedForges   = [];
        foreach ($integrations as $i) {
            $usedForges[] = $i->forge;
        }

        $availableForges = [];
        foreach (GitForge::cases() as $case) {
            if (in_array($case, $usedForges, true)) {
                continue;
            }

            $availableForges[] = $case;
        }

        return $this->renderer->render($request, 'partial::project/webhook-list', [
            'project'         => $project,
            'integrations'    => $integrations,
            'availableForges' => $availableForges,
            'baseUrl'         => $this->baseUrl($request),
            'error'           => null,
        ]);
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($routeId));
            assert($project instanceof Project);

            return $project->teamId->toString() === $activeTeam->id->toString() ? $project : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function baseUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();

        return $uri->getScheme() . '://' . $uri->getHost();
    }
}
