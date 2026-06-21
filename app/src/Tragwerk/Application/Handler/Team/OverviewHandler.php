<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use DateTimeImmutable;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TeamActivityFeed;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Git\BareRepository;

use function array_map;
use function assert;
use function count;
use function iterator_to_array;
use function round;

final readonly class OverviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private DeployJobRepository $deployJobRepository,
        private ServerMetricRepository $serverMetricRepository,
        private BareRepository $bareRepository,
        private TeamActivityFeed $activityFeed,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        /** @var list<Project> $projects */
        $projects = iterator_to_array($this->projectRepository->getAll($activeTeam->id), false);
        /** @var list<Server> $servers */
        $servers = iterator_to_array($this->serverRepository->getAll(teamId: $activeTeam->id), false);

        $projectIds = array_map(static fn (Project $p): string => $p->id->toString(), $projects);

        // Per-project latest deploy → status badge + last-deploy time in the projects table.
        $deployStatus = [];
        $lastDeploy   = [];
        foreach ($projects as $project) {
            $latest                                 = $this->deployJobRepository->getLatestByProject($project->id);
            $deployStatus[$project->id->toString()] = $latest?->status;
            $lastDeploy[$project->id->toString()]   = $latest?->createdAt;
        }

        return $this->renderer->render($request, 'page::team/overview', [
            'projects'     => $projects,
            'deployStatus' => $deployStatus,
            'lastDeploy'   => $lastDeploy,
            'cards'        => $this->buildCards($projects, $servers, $projectIds),
            'activity'     => $this->activityFeed->build($activeTeam->id, $projects, $servers),
        ]);
    }

    /**
     * @param list<Project> $projects
     * @param list<Server>  $servers
     * @param list<string>  $projectIds
     *
     * @return list<array<string, mixed>>
     */
    private function buildCards(array $projects, array $servers, array $projectIds): array
    {
        return [
            $this->activeProjectsCard($projects),
            $this->environmentsCard($projects),
            $this->deploymentsCard($projectIds),
            $this->utilizationCard($servers),
        ];
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<string, mixed>
     */
    private function activeProjectsCard(array $projects): array
    {
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $thisMonth  = 0;
        foreach ($projects as $project) {
            if ($project->createdAt->toDateTime() < $monthStart) {
                continue;
            }

            $thisMonth++;
        }

        $card = [
            'label'     => 'Active projects',
            'labelIcon' => 'bi bi-box-seam',
            'value'     => count($projects),
        ];

        if ($thisMonth > 0) {
            $card['delta']         = $thisMonth . ' this month';
            $card['deltaIcon']     = 'bi-arrow-up-short';
            $card['deltaColorVar'] = '--ok-text';
        }

        return $card;
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<string, mixed>
     */
    private function environmentsCard(array $projects): array
    {
        $total   = 0;
        $preview = 0;
        foreach ($projects as $project) {
            try {
                $parents = $this->bareRepository->getBranchParents($project->id->toString());
            } catch (Throwable) {
                continue;
            }

            $total += count($parents);
            foreach ($parents as $parent) {
                if ($parent === null) {
                    continue;
                }

                $preview++;
            }
        }

        return [
            'label'     => 'Environments',
            'labelIcon' => 'bi bi-diagram-3',
            'value'     => $total,
            'text'      => $preview . ' in preview branches',
        ];
    }

    /**
     * @param list<string> $projectIds
     *
     * @return array<string, mixed>
     */
    private function deploymentsCard(array $projectIds): array
    {
        $counts = $this->deployJobRepository->countByProjectsSince(
            $projectIds,
            new DateTimeImmutable('-24 hours'),
        );

        $card = [
            'label'     => 'Deployments / 24 h',
            'labelIcon' => 'bi bi-rocket-takeoff',
            'value'     => $counts['total'],
        ];

        if ($counts['total'] > 0) {
            $rate                  = round($counts['completed'] / $counts['total'] * 100, 1);
            $card['delta']         = $rate . ' % successful';
            $card['deltaIcon']     = 'bi-check2-circle';
            $card['deltaColorVar'] = '--ok-text';
        }

        return $card;
    }

    /**
     * @param list<Server> $servers
     *
     * @return array<string, mixed>
     */
    private function utilizationCard(array $servers): array
    {
        $sum   = 0.0;
        $count = 0;
        foreach ($servers as $server) {
            $sample = $this->serverMetricRepository->getLatest($server->id);
            if ($sample === null) {
                continue;
            }

            $sum += $sample->cpuPercent;
            $count++;
        }

        $card = [
            'label'     => 'Server utilization',
            'labelIcon' => 'bi bi-hdd-stack',
        ];

        if ($count === 0) {
            $card['value'] = 0;

            return $card;
        }

        $avg           = (int) round($sum / $count);
        $card['value'] = $avg;
        $card['bar']   = $avg;

        return $card;
    }
}
