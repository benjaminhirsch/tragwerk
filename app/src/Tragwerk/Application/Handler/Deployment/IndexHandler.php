<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Deployment;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Deployment\LogListEntry;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;

use function array_slice;
use function assert;
use function count;
use function in_array;
use function is_numeric;
use function is_string;
use function iterator_to_array;
use function max;
use function usort;

final readonly class IndexHandler implements RequestHandlerInterface
{
    private const int PAGE_SIZE = 20;

    private const array TYPES = ['all', 'build', 'deploy'];

    public function __construct(
        private ResponseRenderer $renderer,
        private DeployJobRepository $deployJobRepository,
        private BuildLogRepository $buildLogRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        assert($activeProject instanceof Project);

        $activeBranch = $request->getAttribute('active_environment');
        assert(is_string($activeBranch));

        $params = $request->getQueryParams();
        $offset = max(0, is_numeric($params['offset'] ?? null) ? (int) $params['offset'] : 0);
        $type   = is_string($params['type'] ?? null) && in_array($params['type'], self::TYPES, true)
            ? $params['type']
            : 'all';

        $entries = $this->buildEntries($activeProject, $activeBranch, $offset, $type);

        $hasMore = count($entries) > $offset + self::PAGE_SIZE;
        $entries = array_slice($entries, $offset, self::PAGE_SIZE);

        // Infinite scroll (offset > 0) and live list refresh (fragment flag)
        // render only the list items, swapped into #log-items.
        if ($offset > 0 || isset($params['fragment'])) {
            return $this->renderer->render($request, 'page::deployment/_log_items', [
                'branch'  => $activeBranch,
                'entries' => $entries,
                'offset'  => $offset,
                'hasMore' => $hasMore,
                'type'    => $type,
            ]);
        }

        $activeJobs = $this->deployJobRepository->getActiveByProjectAndBranch(
            $activeProject->id,
            $activeBranch,
        );

        // Filter switches reload the whole panel (filter bar + list) so the
        // active button state is rendered server-side, no client JS needed.
        if (isset($params['panel'])) {
            return $this->renderer->render($request, 'page::deployment/_log_panel', [
                'project'    => $activeProject,
                'branch'     => $activeBranch,
                'entries'    => $entries,
                'activeJobs' => $activeJobs,
                'offset'     => $offset,
                'hasMore'    => $hasMore,
                'type'       => $type,
            ]);
        }

        return $this->renderer->render($request, 'page::deployment/index', [
            'project'    => $activeProject,
            'branch'     => $activeBranch,
            'entries'    => $entries,
            'activeJobs' => $activeJobs,
            'selected'   => $this->resolveSelected(
                $entries,
                $activeJobs,
                $activeProject,
                $activeBranch,
                $params['selected'] ?? null,
            ),
            'offset'     => $offset,
            'hasMore'    => $hasMore,
            'type'       => $type,
        ]);
    }

    /**
     * Build the merged, chronologically descending list of deploy jobs and
     * build logs. Fetches everything up to (offset + PAGE_SIZE + 1) so the
     * caller can slice the requested page and detect whether more remain.
     *
     * @param 'all'|'build'|'deploy' $type
     *
     * @return list<LogListEntry>
     */
    private function buildEntries(Project $project, string $branch, int $offset, string $type): array
    {
        $limit = $offset + self::PAGE_SIZE + 1;

        $entries = [];

        if ($type !== 'build') {
            foreach ($this->deployJobRepository->getPagedByProjectAndBranch($project->id, $branch, $limit, 0) as $job) {
                $entries[] = LogListEntry::fromDeployJob($job);
            }
        }

        if ($type !== 'deploy') {
            $buildLogs = $this->buildLogRepository->getByProjectAndBranch($project->id, $branch);

            foreach (iterator_to_array($buildLogs, false) as $log) {
                $entries[] = LogListEntry::fromBuildLog($log);
            }
        }

        usort(
            $entries,
            static fn (LogListEntry $a, LogListEntry $b): int => $b->sortAt->toDateTime() <=> $a->sortAt->toDateTime(),
        );

        return $entries;
    }

    /**
     * The initially shown terminal entry: an explicitly requested deploy job
     * (e.g. when linked from the environment overview), otherwise the first
     * active deploy job, otherwise the newest entry in the list.
     *
     * @param list<LogListEntry> $entries
     * @param list<DeployJob>    $activeJobs
     */
    private function resolveSelected(
        array $entries,
        array $activeJobs,
        Project $project,
        string $branch,
        mixed $requestedId,
    ): LogListEntry|null {
        if (is_string($requestedId) && DeployJobIdentifier::isValid($requestedId)) {
            try {
                $job = $this->deployJobRepository->getById(DeployJobIdentifier::fromString($requestedId));
                assert($job instanceof DeployJob);

                if ($job->projectId->toString() === $project->id->toString() && $job->branch === $branch) {
                    return LogListEntry::fromDeployJob($job);
                }
            } catch (EntityNotFound) {
                // Fall through to the default selection below.
            }
        }

        if ($activeJobs !== []) {
            return LogListEntry::fromDeployJob($activeJobs[0]);
        }

        return $entries[0] ?? null;
    }
}
