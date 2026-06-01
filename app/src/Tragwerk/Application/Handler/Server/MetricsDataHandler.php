<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use DateTimeImmutable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

use function assert;
use function is_string;
use function round;

final readonly class MetricsDataHandler implements RequestHandlerInterface
{
    private const array RANGES = [
        '1h'  => '-1 hour',
        '6h'  => '-6 hours',
        '24h' => '-24 hours',
    ];

    public function __construct(
        private ServerRepository $serverRepository,
        private ServerMetricRepository $metricRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->resolveServer($request);

        if (! $server instanceof Server) {
            return new EmptyResponse(404);
        }

        $rangeKey = $request->getQueryParams()['range'] ?? '1h';
        $modifier = self::RANGES[is_string($rangeKey) ? $rangeKey : '1h'] ?? self::RANGES['1h'];

        $to   = new DateTimeImmutable();
        $from = $to->modify($modifier);

        $samples = $this->metricRepository->getRange($server->id, $from, $to);

        $t    = [];
        $cpu  = [];
        $mem  = [];
        $disk = [];
        $load = [];

        foreach ($samples as $sample) {
            $t[]    = $sample->sampledAt->toDateTime()->getTimestamp();
            $cpu[]  = round($sample->cpuPercent, 1);
            $mem[]  = $this->percent($sample->memUsedBytes, $sample->memTotalBytes);
            $disk[] = $this->percent($sample->diskUsedBytes, $sample->diskTotalBytes);
            $load[] = round($sample->load1, 2);
        }

        return new JsonResponse([
            't'    => $t,
            'cpu'  => $cpu,
            'mem'  => $mem,
            'disk' => $disk,
            'load' => $load,
        ]);
    }

    private function percent(int $used, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($used / $total * 100, 1);
    }

    private function resolveServer(ServerRequestInterface $request): Server|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ServerIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $server = $this->serverRepository->getById(ServerIdentifier::fromString($routeId));
            assert($server instanceof Server);

            if ($server->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $server;
        } catch (Throwable) {
            return null;
        }
    }
}
