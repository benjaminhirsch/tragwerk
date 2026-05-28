<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Ramsey\Uuid\Uuid;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Repository\EnvironmentRepository as EnvironmentRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function array_map;
use function is_int;
use function is_string;

final readonly class EnvironmentRepository implements EnvironmentRepositoryInterface
{
    private const string TABLE = 'project_environments';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /** @return string[] */
    #[Override]
    public function getActiveBranches(ProjectIdentifier $projectId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('branch')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->setParameter('project_id', $projectId->toString());

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(
            static fn (array $row): string => is_string($row['branch']) ? $row['branch'] : '',
            $rows,
        );
    }

    #[Override]
    public function isActive(ProjectIdentifier $projectId, string $branch): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch);

        $result = $qb->executeQuery()->fetchOne();

        return (is_string($result) || is_int($result)) && (int) $result > 0;
    }

    #[Override]
    public function activate(ProjectIdentifier $projectId, string $branch): void
    {
        try {
            $this->connection->executeStatement(
                'INSERT INTO ' . self::TABLE . ' (id, project_id, branch) VALUES (:id, :project_id, :branch)'
                . ' ON CONFLICT (project_id, branch) DO NOTHING',
                [
                    'id'         => Uuid::uuid7()->toString(),
                    'project_id' => $projectId->toString(),
                    'branch'     => $branch,
                ],
            );
        } catch (Exception $e) {
            throw EntityCreationFailed::create(self::TABLE, $projectId, $e);
        }
    }

    #[Override]
    public function deactivate(ProjectIdentifier $projectId, string $branch): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM ' . self::TABLE . ' WHERE project_id = :project_id AND branch = :branch',
                [
                    'project_id' => $projectId->toString(),
                    'branch'     => $branch,
                ],
            );
        } catch (Exception $e) {
            throw EntityDeletionFailed::create($projectId, $e);
        }
    }
}
