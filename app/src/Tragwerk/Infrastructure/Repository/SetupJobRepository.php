<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Override;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\SetupJobRepository as SetupJobRepositoryInterface;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function array_map;

final class SetupJobRepository extends GenericRepository implements SetupJobRepositoryInterface
{
    #[Override]
    public function getLatestForServer(ServerIdentifier $serverId): SetupJob|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::SETUP_JOB))
            ->where($qb->expr()->eq('server_id', ':server_id'))
            ->setParameter('server_id', $serverId->toString())
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1);

        try {
            $row = $qb->executeQuery()->fetchAssociative();
            if ($row === false) {
                return null;
            }

            return $this->map($row, SetupJob::class);
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(SetupJob::class, $e);
        }
    }

    #[Override]
    public function getCompletedServerIds(array $serverIds): array
    {
        if ($serverIds === []) {
            return [];
        }

        $ids = array_map(static fn (ServerIdentifier $id) => $id->toString(), $serverIds);

        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT server_id')
            ->from(EntityHelper::getDbTableName(EntityType::SETUP_JOB))
            ->where($qb->expr()->in('server_id', ':ids'))
            ->andWhere($qb->expr()->eq('status', ':status'))
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->setParameter('status', SetupJobStatus::Completed->value);

        try {
            return array_map(
                static function (array $row): string {
                    /** @var array<string, string> $row */
                    return $row['server_id'];
                },
                $qb->executeQuery()->fetchAllAssociative(),
            );
        } catch (Exception) {
            return [];
        }
    }

    #[Override]
    public function updateStatus(SetupJobIdentifier $id, SetupJobStatus $status): void
    {
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE setup_jobs SET status = :status, updated_at = :now WHERE id = :id',
                ['status' => $status->value, 'now' => (string) TimestampImmutable::now(), 'id' => $id->toString()],
            );

            if ($affected === 0) {
                throw EntityNotFound::fromIdentifier($id);
            }
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    #[Override]
    public function appendOutput(SetupJobIdentifier $id, string $text): void
    {
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE setup_jobs SET output = output || :text, updated_at = :now WHERE id = :id',
                ['text' => $text, 'now' => (string) TimestampImmutable::now(), 'id' => $id->toString()],
            );

            if ($affected === 0) {
                throw EntityNotFound::fromIdentifier($id);
            }
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }
}
