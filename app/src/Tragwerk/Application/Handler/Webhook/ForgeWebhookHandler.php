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
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Event\BuildLogCreated;
use Tragwerk\Domain\Event\EnvironmentDeleted;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;

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
        private BareRepository $bareRepository,
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

        // The push landed on the external forge, not on Tragwerk's own remote, so
        // the commit is not yet in the local bare repo. Fetch it first — config
        // validation and the build both read the source from there.
        if ($payload->cloneUrl !== null) {
            try {
                $this->bareRepository->fetch(
                    $project->id->toString(),
                    $payload->cloneUrl,
                    $payload->branch,
                    $integration->accessToken,
                );
            } catch (Throwable) {
                // Do not surface the git error verbatim — it may contain the
                // token-bearing URL. A generic, actionable message is persisted.
                $this->eventDispatcher->dispatch(new BuildLogCreated(new BuildLog(
                    id:        BuildLogIdentifier::create(),
                    projectId: $project->id,
                    branch:    $payload->branch,
                    type:      BuildLogType::WEBHOOK,
                    message:   'Failed to fetch "' . $payload->branch . '" from the remote repository — '
                        . 'check the repository access and, for private repos, the integration access token.',
                    createdAt: TimestampImmutable::now(),
                )));

                return new JsonResponse(['status' => 'error'], 502);
            }
        }

        $this->buildDispatcher->dispatch($project, $payload->branch, $payload->commitSha, BuildLogType::WEBHOOK);

        return new JsonResponse(['status' => 'ok']);
    }
}
