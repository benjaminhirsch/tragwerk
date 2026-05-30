<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Queue\Message\SyncEnvironmentData;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Event\DeployJobCreated;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;

use function array_slice;
use function count;
use function is_array;
use function is_string;

final readonly class SyncEnvironmentDataHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private BareRepository $bareRepository,
        private DeployJobRepository $deployJobRepository,
        private EventDispatcherInterface $eventDispatcher,
        private Producer $producer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $body   = $request->getParsedBody();
        $branch = is_array($body) && is_string($body['branch'] ?? null) ? $body['branch'] : null;

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        try {
            $commits = $this->bareRepository->getCommits($project->id->toString(), $branch, 1);
        } catch (Throwable) {
            $commits = [];
        }

        if ($commits === []) {
            return new EmptyResponse(400);
        }

        $deployJob = new DeployJob(
            id:        DeployJobIdentifier::create(),
            projectId: $project->id,
            branch:    $branch,
            commitSha: $commits[0]->hash,
            status:    DeployJobStatus::Pending,
            output:    '',
            createdAt: TimestampImmutable::now(),
            updatedAt: TimestampImmutable::now(),
        );

        $this->eventDispatcher->dispatch(new DeployJobCreated($deployJob));

        $this->producer->sendMessage(new SyncEnvironmentData(
            projectId:   $project->id->toString(),
            branch:      $branch,
            deployJobId: $deployJob->id->toString(),
        ));

        $jobs       = $this->deployJobRepository->getPagedByProjectAndBranch($project->id, $branch, 21, 0);
        $hasMore    = count($jobs) > 20;
        $jobs       = array_slice($jobs, 0, 20);
        $activeJobs = $this->deployJobRepository->getActiveByProjectAndBranch($project->id, $branch);

        return $this->renderer->render($request, 'page::project/_deploy_log', [
            'project'      => $project,
            'branch'       => $branch,
            'jobs'         => $jobs,
            'activeJobs'   => $activeJobs,
            'offset'       => 0,
            'hasMore'      => $hasMore,
            'forcePolling' => true,
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

            if (! $project instanceof Project) {
                return null;
            }

            return $project->teamId->toString() === $activeTeam->id->toString() ? $project : null;
        } catch (Throwable) {
            return null;
        }
    }
}
