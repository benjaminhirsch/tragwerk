<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Webhook;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Service\BuildDispatcher;
use Tragwerk\Application\Webhook\AdapterRegistry;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Event\EnvironmentDeleted;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_string;

final readonly class ForgeWebhookHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectWebhookRepository $webhookRepository,
        private AdapterRegistry $adapterRegistry,
        private BuildDispatcher $buildDispatcher,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $forgeSlug = $request->getAttribute('forge');

        if (! is_string($forgeSlug)) {
            return new EmptyResponse(404);
        }

        $forge = GitForge::tryFromRouteSlug($forgeSlug);

        if ($forge === null) {
            return new EmptyResponse(404);
        }

        $projectId = $request->getAttribute('projectId');

        if (! is_string($projectId) || ! ProjectIdentifier::isValid($projectId)) {
            return new EmptyResponse(400);
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($projectId));
            assert($project instanceof Project);
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        $integration = $this->webhookRepository->findByProjectAndForge($project->id, $forge);

        if ($integration === null) {
            return new EmptyResponse(401);
        }

        $adapter = $this->adapterRegistry->get($forge);

        if (! $adapter->verify($request, $integration->secret)) {
            return new EmptyResponse(401);
        }

        $payload = $adapter->extractPushPayload($request);

        if ($payload === null) {
            return new EmptyResponse(200);
        }

        if ($payload->deleted) {
            $this->eventDispatcher->dispatch(new EnvironmentDeleted($project->id, $payload->branch));

            return new JsonResponse(['status' => 'ok']);
        }

        $this->buildDispatcher->dispatch($project, $payload->branch, $payload->commitSha, BuildLogType::WEBHOOK);

        return new JsonResponse(['status' => 'ok']);
    }
}
