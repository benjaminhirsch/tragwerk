<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Webhook;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Service\BuildDispatcher;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_array;
use function is_string;

final readonly class GitPushHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BuildDispatcher $buildDispatcher,
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

        $this->buildDispatcher->dispatch($project, $branch, $newSha);

        return new JsonResponse(['status' => 'ok']);
    }
}
