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
use Tragwerk\Application\Queue\Message\BuildEnvironment;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Config\ConfigValidator;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Event\BuildLogCreated;
use Tragwerk\Domain\Repository\EnvironmentRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function implode;
use function is_array;
use function is_string;

final readonly class GitPushHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BareRepository $bareRepository,
        private ConfigValidator $configValidator,
        private EventDispatcherInterface $eventDispatcher,
        private EnvironmentRepository $environmentRepository,
        private Producer $producer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (! is_array($body)) {
            return new EmptyResponse(400);
        }

        $projectId = $body['projectId'] ?? null;
        $branch    = $body['branch'] ?? null;
        $newSha    = $body['newSha'] ?? null;

        if (! is_string($projectId) || ! ProjectIdentifier::isValid($projectId)) {
            return new EmptyResponse(400);
        }

        if (! is_string($branch) || $branch === '') {
            return new EmptyResponse(400);
        }

        if (! is_string($newSha) || $newSha === '') {
            return new EmptyResponse(400);
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($projectId));
            assert($project instanceof Project);
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        [$message, $configValid] = $this->validateConfig($project->id->toString(), $newSha);

        $this->eventDispatcher->dispatch(new BuildLogCreated(new BuildLog(
            id:        BuildLogIdentifier::create(),
            projectId: $project->id,
            branch:    $branch,
            type:      BuildLogType::PUSH,
            message:   $message,
            createdAt: TimestampImmutable::now(),
        )));

        $isProtected = $branch === 'main' || $branch === 'master';
        $isActive    = $isProtected || $this->environmentRepository->isActive($project->id, $branch);

        if ($configValid && $isActive) {
            $this->producer->sendMessage(new BuildEnvironment(
                projectId: $project->id->toString(),
                branch:    $branch,
                commitSha: $newSha,
            ));
        }

        return new JsonResponse(['status' => 'ok']);
    }

    /** @return array{string, bool} */
    private function validateConfig(string $projectId, string $commitSha): array
    {
        $content = $this->bareRepository->getFileContent($projectId, $commitSha, '.tragwerk/config.xml');

        if ($content === null || $content === '') {
            return ['No .tragwerk/config.xml found — skipping validation', false];
        }

        $errors = $this->configValidator->validate($content);

        if ($errors === []) {
            return ['Configuration validated successfully', true];
        }

        return ['Configuration is invalid:' . "\n" . implode("\n", $errors), false];
    }
}
