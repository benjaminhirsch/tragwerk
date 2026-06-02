<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use DateTimeImmutable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\AppMetricRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_string;

final readonly class EnvironmentMetricsDataHandler implements RequestHandlerInterface
{
    private const array RANGES = [
        '1h'  => ['-1 hour', 60],
        '6h'  => ['-6 hours', 300],
        '24h' => ['-24 hours', 900],
        '7d'  => ['-7 days', 3600],
        '30d' => ['-30 days', 21600],
    ];

    public function __construct(
        private ProjectRepository $projectRepository,
        private AppMetricRepository $metricRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $params = $request->getQueryParams();
        $branch = is_string($params['branch'] ?? null) ? $params['branch'] : null;

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        $rangeKey            = is_string($params['range'] ?? null) ? $params['range'] : '1h';
        [$modifier, $bucket] = self::RANGES[$rangeKey] ?? self::RANGES['1h'];

        $to   = new DateTimeImmutable();
        $from = $to->modify($modifier);

        $rows = $this->metricRepository->getAggregated($project->id, $branch, $from, $to, $bucket);

        $t         = [];
        $busy      = [];
        $total     = [];
        $ready     = [];
        $queue     = [];
        $reqRate   = [];
        $errPct    = [];
        $latencyMs = [];

        foreach ($rows as $row) {
            $t[]         = $row['t'];
            $busy[]      = $row['busy'];
            $total[]     = $row['total'];
            $ready[]     = $row['ready'];
            $queue[]     = $row['queue'];
            $reqRate[]   = $row['reqRate'];
            $errPct[]    = $row['errPct'];
            $latencyMs[] = $row['latencyMs'];
        }

        return new JsonResponse([
            't'         => $t,
            'busy'      => $busy,
            'total'     => $total,
            'ready'     => $ready,
            'queue'     => $queue,
            'reqRate'   => $reqRate,
            'errPct'    => $errPct,
            'latencyMs' => $latencyMs,
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

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
