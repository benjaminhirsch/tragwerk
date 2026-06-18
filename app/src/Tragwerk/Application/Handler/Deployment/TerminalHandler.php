<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Deployment;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;

use function assert;
use function is_string;

final readonly class TerminalHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private DeployJobRepository $deployJobRepository,
        private BuildLogRepository $buildLogRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $params = $request->getQueryParams();
        $kind   = is_string($params['kind'] ?? null) ? $params['kind'] : null;
        $id     = is_string($params['id'] ?? null) ? $params['id'] : null;

        if ($id === null) {
            return new EmptyResponse(404);
        }

        return match ($kind) {
            'deploy' => $this->renderDeploy($request, $project, $id),
            'build'  => $this->renderBuild($request, $project, $id),
            default  => new EmptyResponse(404),
        };
    }

    private function renderDeploy(ServerRequestInterface $request, Project $project, string $id): ResponseInterface
    {
        if (! DeployJobIdentifier::isValid($id)) {
            return new EmptyResponse(404);
        }

        try {
            $job = $this->deployJobRepository->getById(DeployJobIdentifier::fromString($id));
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        assert($job instanceof DeployJob);

        if ($job->projectId->toString() !== $project->id->toString()) {
            return new EmptyResponse(404);
        }

        return $this->renderer->render($request, 'page::deployment/_terminal', [
            'project'   => $project,
            'kind'      => 'deploy',
            'deployJob' => $job,
            'buildLog'  => null,
        ]);
    }

    private function renderBuild(ServerRequestInterface $request, Project $project, string $id): ResponseInterface
    {
        if (! BuildLogIdentifier::isValid($id)) {
            return new EmptyResponse(404);
        }

        try {
            $log = $this->buildLogRepository->getById(BuildLogIdentifier::fromString($id));
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        if ($log->projectId->toString() !== $project->id->toString()) {
            return new EmptyResponse(404);
        }

        return $this->renderer->render($request, 'page::deployment/_terminal', [
            'project'   => $project,
            'kind'      => 'build',
            'deployJob' => null,
            'buildLog'  => $log,
        ]);
    }
}
