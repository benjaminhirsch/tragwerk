<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Ramsey\Uuid\Uuid;
use Tragwerk\Domain\Model\CronRun;
use Tragwerk\Domain\Repository\CronRunRepository as CronRunRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function array_map;

/**
 * @phpstan-type RowShape array{
 *     project_id: string, branch: string, app_slug: string, job_name: string, command: string,
 *     schedule: string|null, started_at: string, finished_at: string|null,
 *     succeeded: bool|int|string|null, output: string|null
 * }
 */
final readonly class CronRunRepository implements CronRunRepositoryInterface
{
    private const string TABLE     = 'cron_runs';
    private const string TS_FORMAT = 'Y-m-d H:i:s.u P';

    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function store(CronRun $run): void
    {
        // ON CONFLICT keeps the row idempotent across overlapping ingest windows; COALESCE lets a
        // later "finished" line fill in fields without clobbering data already captured.
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO cron_runs (
                id, project_id, branch, app_slug, job_name, command, schedule,
                started_at, finished_at, succeeded, output
            )
            SELECT :id, :project_id, :branch, :app_slug, :job_name, :command, :schedule,
                   :started_at, :finished_at, :succeeded, :output
            WHERE EXISTS (SELECT 1 FROM projects WHERE id = :project_id)
            ON CONFLICT (project_id, branch, command, started_at) DO UPDATE SET
                finished_at = COALESCE(EXCLUDED.finished_at, cron_runs.finished_at),
                succeeded   = COALESCE(EXCLUDED.succeeded, cron_runs.succeeded),
                output      = COALESCE(EXCLUDED.output, cron_runs.output),
                job_name    = EXCLUDED.job_name,
                schedule    = COALESCE(EXCLUDED.schedule, cron_runs.schedule)
            SQL,
            [
                'id'          => Uuid::uuid7()->toString(),
                'project_id'  => $run->projectId,
                'branch'      => $run->branch,
                'app_slug'    => $run->appSlug,
                'job_name'    => $run->jobName,
                'command'     => $run->command,
                'schedule'    => $run->schedule,
                'started_at'  => $run->startedAt->toString(),
                'finished_at' => $run->finishedAt?->toString(),
                'succeeded'   => $run->succeeded,
                'output'      => $run->output,
            ],
            ['succeeded' => 'boolean'],
        );
    }

    /** @return list<CronRun> */
    #[Override]
    public function recent(ProjectIdentifier $projectId, string $branch, int $limit = 50): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('project_id', ':pid'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->orderBy('started_at', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('pid', $projectId->toString())
            ->setParameter('branch', $branch);

        /** @var list<RowShape> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map($this->hydrate(...), $rows);
    }

    /** @return array<string, CronRun> */
    #[Override]
    public function latestPerJob(ProjectIdentifier $projectId, string $branch): array
    {
        // DISTINCT ON (command) keeps only the newest run per job.
        $sql = <<<'SQL'
            SELECT DISTINCT ON (command) *
            FROM cron_runs
            WHERE project_id = :pid AND branch = :branch
            ORDER BY command, started_at DESC
            SQL;

        /** @var list<RowShape> $rows */
        $rows = $this->connection->executeQuery($sql, [
            'pid'    => $projectId->toString(),
            'branch' => $branch,
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['command']] = $this->hydrate($row);
        }

        return $result;
    }

    #[Override]
    public function pruneOlderThan(DateTimeImmutable $threshold): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE started_at < :threshold',
            ['threshold' => $threshold->format(self::TS_FORMAT)],
        );
    }

    /** @param RowShape $row */
    private function hydrate(array $row): CronRun
    {
        return new CronRun(
            projectId:  $row['project_id'],
            branch:     $row['branch'],
            appSlug:    $row['app_slug'],
            jobName:    $row['job_name'],
            command:    $row['command'],
            schedule:   $row['schedule'],
            startedAt:  TimestampImmutable::fromDateTime(new DateTimeImmutable($row['started_at'])),
            finishedAt: $row['finished_at'] !== null
                ? TimestampImmutable::fromDateTime(new DateTimeImmutable($row['finished_at']))
                : null,
            succeeded:  $row['succeeded'] !== null ? (bool) $row['succeeded'] : null,
            output:     $row['output'],
        );
    }
}
