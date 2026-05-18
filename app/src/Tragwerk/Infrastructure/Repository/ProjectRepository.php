<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\ProjectRepository as ProjectRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function implode;

final class ProjectRepository extends GenericRepository implements ProjectRepositoryInterface
{
    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::PROJECT));

        if ($ids !== null) {
            $qb->andWhere($qb->expr()->in('id', ':ids'));
            $qb->setParameter('ids', $ids);
        }

        if ($names !== null) {
            $qb->andWhere($qb->expr()->in('name', ':names'));
            $qb->setParameter('names', implode(',', $names));
        }

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Project::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Project::class, $e);
        }
    }

    #[Override]
    public function getByUserId(UserIdentifier $userId): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.*')
            ->from(EntityHelper::getDbTableName(EntityType::PROJECT), 'p')
            ->innerJoin('p', 'project_users', 'pu', 'pu.project_id = p.id')
            ->where($qb->expr()->eq('pu.user_id', ':user_id'))
            ->orderBy('p.name', 'ASC')
            ->setParameter('user_id', $userId->toString());

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Project::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Project::class, $e);
        }
    }

    #[Override]
    public function assignUsers(ProjectIdentifier $projectId, array $userIds): void
    {
        $assignedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s.u');

        try {
            foreach ($userIds as $userId) {
                $this->connection->insert('project_users', [
                    'project_id'  => $projectId->toString(),
                    'user_id'     => $userId->toString(),
                    'assigned_at' => $assignedAt,
                ]);
            }
        } catch (Exception $e) {
            throw EntityCreationFailed::create(UserIdentifier::class, $projectId, $e);
        }
    }
}
