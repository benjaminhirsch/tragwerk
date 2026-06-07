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
use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Event\WebhookIntegrationCreated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

use function assert;
use function bin2hex;
use function in_array;
use function is_array;
use function is_string;
use function random_bytes;

final readonly class CreateWebhookIntegrationHandler implements RequestHandlerInterface
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

        $body      = $request->getParsedBody();
        $forgeSlug = is_array($body) && is_string($body['forge'] ?? null) ? $body['forge'] : '';
        $forge     = GitForge::tryFromRouteSlug($forgeSlug);

        $error = null;

        if ($forge === null) {
            $error = 'Invalid forge type.';
        } elseif ($this->webhookRepository->findByProjectAndForge($project->id, $forge) !== null) {
            $error = 'An integration for this forge already exists.';
        }

        if ($error === null && $forge !== null) {
            $integration = new ProjectWebhook(
                id:        WebhookIntegrationIdentifier::create(),
                projectId: $project->id,
                forge:     $forge,
                secret:    bin2hex(random_bytes(32)),
                createdAt: TimestampImmutable::now(),
            );
            $this->eventDispatcher->dispatch(new WebhookIntegrationCreated($integration));
        }

        return $this->renderList($request, $project, $error);
    }

    private function renderList(
        ServerRequestInterface $request,
        Project $project,
        string|null $error,
    ): ResponseInterface {
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
            'error'           => $error,
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
