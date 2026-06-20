<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Metrics;

use DateTimeImmutable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\AppMetricRepository;

use function assert;
use function is_string;

final readonly class DataHandler implements RequestHandlerInterface
{
    private const array RANGES = [
        '1h'  => ['-1 hour', 60],
        '6h'  => ['-6 hours', 300],
        '24h' => ['-24 hours', 900],
        '7d'  => ['-7 days', 3600],
    ];

    public function __construct(
        private AppMetricRepository $metricRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $this->resolveBranch($request);

        if ($branch === null) {
            return new EmptyResponse(400);
        }

        $params              = $request->getQueryParams();
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

    private function resolveBranch(ServerRequestInterface $request): string|null
    {
        $param = $request->getQueryParams()['branch'] ?? null;
        if (is_string($param) && $param !== '') {
            return $param;
        }

        $active = $request->getAttribute('active_environment');

        return is_string($active) && $active !== '' ? $active : null;
    }
}
