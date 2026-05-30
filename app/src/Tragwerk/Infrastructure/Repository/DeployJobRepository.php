<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Override;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\DeployJobRepository as DeployJobRepositoryInterface;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function array_map;

final class DeployJobRepository extends GenericRepository implements DeployJobRepositoryInterface
{
    #[Override]
    public function getLatestByProjectAndBranch(ProjectIdentifier $projectId, string $branch): DeployJob|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::DEPLOY_JOB))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1);

        try {
            $row = $qb->executeQuery()->fetchAssociative();
            if ($row === false) {
                return null;
            }

            return $this->map($row, DeployJob::class);
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(DeployJob::class, $e);
        }
    }

    /**
     * @param string[] $branches
     *
     * @return array<string, DeployJobStatus>
     */
    #[Override]
    public function getLatestStatusByProjectAndBranches(ProjectIdentifier $projectId, array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT ON (branch) branch, status
                 FROM ' . EntityHelper::getDbTableName(EntityType::DEPLOY_JOB) . '
                 WHERE project_id = :project_id AND branch IN (:branches)
                 ORDER BY branch, created_at DESC',
                ['project_id' => $projectId->toString(), 'branches' => $branches],
                ['branches' => ArrayParameterType::STRING],
            );
        } catch (Exception) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            /** @var array{branch: string, status: string} $row */
            $status = DeployJobStatus::tryFrom($row['status']);
            if ($status === null) {
                continue;
            }

            $result[$row['branch']] = $status;
        }

        return $result;
    }

    /** @return list<DeployJob> */
    #[Override]
    public function getActiveByProjectAndBranch(ProjectIdentifier $projectId, string $branch): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::DEPLOY_JOB))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->andWhere($qb->expr()->in('status', ':statuses'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->setParameter('statuses', ['pending', 'running'], ArrayParameterType::STRING)
            ->orderBy('created_at', 'ASC');

        try {
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return array_map(fn (array $row) => $this->map($row, DeployJob::class), $rows);
        } catch (MappingError | Exception) {
            return [];
        }
    }

    #[Override]
    public function hasCompletedDeploy(ProjectIdentifier $projectId, string $branch): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(EntityHelper::getDbTableName(EntityType::DEPLOY_JOB))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->andWhere($qb->expr()->eq('status', ':status'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->setParameter('status', DeployJobStatus::Completed->value)
            ->setMaxResults(1);

        try {
            return $qb->executeQuery()->fetchOne() !== false;
        } catch (Exception) {
            return false;
        }
    }

    /** @return list<DeployJob> */
    #[Override]
    public function getPagedByProjectAndBranch(
        ProjectIdentifier $projectId,
        string $branch,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::DEPLOY_JOB))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        try {
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return array_map(fn (array $row) => $this->map($row, DeployJob::class), $rows);
        } catch (MappingError | Exception) {
            return [];
        }
    }

    #[Override]
    public function updateStatus(DeployJobIdentifier $id, DeployJobStatus $status): void
    {
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE deploy_jobs SET status = :status, updated_at = :now WHERE id = :id',
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
    public function appendOutput(DeployJobIdentifier $id, string $text): void
    {
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE deploy_jobs SET output = output || :text, updated_at = :now WHERE id = :id',
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
