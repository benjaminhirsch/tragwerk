<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\BuildLogRepository as BuildLogRepositoryInterface;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final readonly class BuildLogRepository implements BuildLogRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private MapperBuilder $mapperBuilder,
    ) {
    }

    #[Override]
    public function create(BuildLog $log): void
    {
        try {
            $this->connection->insert(EntityHelper::getDbTableName(EntityType::BUILD_LOG), [
                'id'         => $log->id->toString(),
                'project_id' => $log->projectId->toString(),
                'branch'     => $log->branch,
                'type'       => $log->type->value,
                'message'    => $log->message,
                'created_at' => $log->createdAt->format('Y-m-d H:i:s.u'),
            ]);
        } catch (Exception $e) {
            throw EntityCreationFailed::create(BuildLog::class, $log->id, $e);
        }
    }

    #[Override]
    public function getById(BuildLogIdentifier $id): BuildLog
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::BUILD_LOG))
            ->where($qb->expr()->eq('id', ':id'))
            ->setParameter('id', $id->toString());

        try {
            $row = $qb->executeQuery()->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromIdentifier($id);
            }

            return $this->mapperBuilder->mapper()->map(BuildLog::class, $row);
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(BuildLog::class, $e);
        }
    }

    #[Override]
    public function getByProjectAndBranch(ProjectIdentifier $projectId, string $branch): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::BUILD_LOG))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->addOrderBy('created_at', 'DESC');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->mapperBuilder->mapper()->map(BuildLog::class, $row);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(BuildLog::class, $e);
        }
    }

    #[Override]
    public function getLatestByProjectAndBranch(
        ProjectIdentifier $projectId,
        string $branch,
        int $limit = 10,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::BUILD_LOG))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->addOrderBy('created_at', 'DESC')
            ->setMaxResults($limit);

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->mapperBuilder->mapper()->map(BuildLog::class, $row);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(BuildLog::class, $e);
        }
    }
}
