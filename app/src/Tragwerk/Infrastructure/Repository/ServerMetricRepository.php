<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Ramsey\Uuid\Uuid;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\Repository\ServerMetricRepository as ServerMetricRepositoryInterface;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function array_map;

/**
 * @phpstan-type RowShape array{
 *     server_id: string,
 *     sampled_at: string,
 *     cpu_percent: string,
 *     mem_used_bytes: string,
 *     mem_total_bytes: string,
 *     disk_used_bytes: string,
 *     disk_total_bytes: string,
 *     load1: string
 * }
 */
final readonly class ServerMetricRepository implements ServerMetricRepositoryInterface
{
    private const string TABLE     = 'server_metrics';
    private const string TS_FORMAT = 'Y-m-d H:i:s.u P';

    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function store(ServerMetricSample $sample): void
    {
        $this->connection->insert(self::TABLE, [
            'id'               => Uuid::uuid7()->toString(),
            'server_id'        => $sample->serverId->toString(),
            'sampled_at'       => $sample->sampledAt->toString(),
            'cpu_percent'      => $sample->cpuPercent,
            'mem_used_bytes'   => $sample->memUsedBytes,
            'mem_total_bytes'  => $sample->memTotalBytes,
            'disk_used_bytes'  => $sample->diskUsedBytes,
            'disk_total_bytes' => $sample->diskTotalBytes,
            'load1'            => $sample->load1,
        ]);
    }

    /** @return list<ServerMetricSample> */
    #[Override]
    public function getRange(ServerIdentifier $serverId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('server_id', ':sid'))
            ->andWhere($qb->expr()->gte('sampled_at', ':from'))
            ->andWhere($qb->expr()->lte('sampled_at', ':to'))
            ->orderBy('sampled_at', 'ASC')
            ->setParameter('sid', $serverId->toString())
            ->setParameter('from', $from->format(self::TS_FORMAT))
            ->setParameter('to', $to->format(self::TS_FORMAT));

        /** @var list<RowShape> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map($this->hydrate(...), $rows);
    }

    #[Override]
    public function pruneOlderThan(DateTimeImmutable $threshold): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE sampled_at < :threshold',
            ['threshold' => $threshold->format(self::TS_FORMAT)],
        );
    }

    /** @param RowShape $row */
    private function hydrate(array $row): ServerMetricSample
    {
        // The PostgreSQL driver returns timestamptz in its own offset format (e.g. "+00" instead of
        // "+00:00"); DateTimeImmutable's constructor parses that reliably, unlike createFromFormat.
        $sampledAt = TimestampImmutable::fromDateTime(new DateTimeImmutable($row['sampled_at']));

        return new ServerMetricSample(
            serverId:       ServerIdentifier::fromString($row['server_id']),
            sampledAt:      $sampledAt,
            cpuPercent:     (float) $row['cpu_percent'],
            memUsedBytes:   (int) $row['mem_used_bytes'],
            memTotalBytes:  (int) $row['mem_total_bytes'],
            diskUsedBytes:  (int) $row['disk_used_bytes'],
            diskTotalBytes: (int) $row['disk_total_bytes'],
            load1:          (float) $row['load1'],
        );
    }
}
