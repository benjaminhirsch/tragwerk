<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\TeamRepository as TeamRepositoryInterface;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function implode;

final class TeamRepository extends GenericRepository implements TeamRepositoryInterface
{
    private const string TEAM_USERS_TABLE = 'team_users';

    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
        array|null $ownerIds = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::TEAM));

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
                yield $this->map($row, Team::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Team::class, $e);
        }
    }

    #[Override]
    public function getByUserId(UserIdentifier $userId): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('t.*')
            ->from(EntityHelper::getDbTableName(EntityType::TEAM), 't')
            ->innerJoin('t', 'team_users', 'tu', 'tu.team_id = t.id')
            ->where($qb->expr()->eq('tu.user_id', ':user_id'))
            ->orderBy('t.name', 'ASC')
            ->setParameter('user_id', $userId->toString());

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Team::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Team::class, $e);
        }
    }

    #[Override]
    public function assignUsers(TeamIdentifier $teamId, array $userIds): void
    {
        $assignedAt = new DateTimeImmutable()->format('Y-m-d H:i:s.u');

        try {
            foreach ($userIds as $userId) {
                $this->connection->insert('team_users', [
                    'team_id'     => $teamId->toString(),
                    'user_id'     => $userId->toString(),
                    'assigned_at' => $assignedAt,
                ]);
            }
        } catch (Exception $e) {
            throw EntityCreationFailed::create(UserIdentifier::class, $teamId, $e);
        }
    }

    #[Override]
    public function getUsersByTeamId(TeamIdentifier $teamId): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('u.*')
            ->from(EntityHelper::getDbTableName(EntityType::USER), 'u')
            ->innerJoin('u', self::TEAM_USERS_TABLE, 'tu', 'tu.user_id = u.id')
            ->where($qb->expr()->eq('tu.team_id', ':team_id'))
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.firstname', 'ASC')
            ->setParameter('team_id', $teamId->toString());

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, User::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(User::class, $e);
        }
    }

    #[Override]
    public function removeUser(TeamIdentifier $teamId, UserIdentifier $userId): void
    {
        try {
            $this->connection->delete(self::TEAM_USERS_TABLE, [
                'team_id' => $teamId->toString(),
                'user_id' => $userId->toString(),
            ]);
        } catch (Exception $e) {
            throw EntityDeletionFailed::create($userId, $e);
        }
    }
}
