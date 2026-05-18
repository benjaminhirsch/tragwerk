<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\ProjectRepository as ProjectRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function implode;

final class ProjectRepository extends GenericRepository implements ProjectRepositoryInterface
{
    private const string PROJECT_USERS_TABLE = 'project_users';

    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
        array|null $ownerIds = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::PROJECT));

        if ($ids !== null) {
            $qb->andWhere($qb->expr()->in('id', ':ids'));
            $qb->setParameter('ids', $ids, Types::SIMPLE_ARRAY);
        }

        if ($names !== null) {
            $qb->andWhere($qb->expr()->in('name', ':names'));
            $qb->setParameter('names', implode(',', $names));
        }

        if ($ownerIds !== null) {
            $qb->andWhere($qb->expr()->in('owner_id', ':owner_ids'));
            $qb->setParameter('owner_ids', $ownerIds, Types::SIMPLE_ARRAY);
        }

        try {
            foreach ($qb->addOrderBy('created_at', 'DESC')->executeQuery()->iterateAssociative() as $row) {
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

    #[Override]
    public function getUsersByProjectId(ProjectIdentifier $projectId): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('u.*')
            ->from(EntityHelper::getDbTableName(EntityType::USER), 'u')
            ->innerJoin('u', self::PROJECT_USERS_TABLE, 'pu', 'pu.user_id = u.id')
            ->where($qb->expr()->eq('pu.project_id', ':project_id'))
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.firstname', 'ASC')
            ->setParameter('project_id', $projectId->toString());

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, User::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(User::class, $e);
        }
    }

    #[Override]
    public function removeUser(ProjectIdentifier $projectId, UserIdentifier $userId): void
    {
        try {
            $this->connection->delete(self::PROJECT_USERS_TABLE, [
                'project_id' => $projectId->toString(),
                'user_id'    => $userId->toString(),
            ]);
        } catch (Exception $e) {
            throw EntityDeletionFailed::create($userId, $e);
        }
    }
}
