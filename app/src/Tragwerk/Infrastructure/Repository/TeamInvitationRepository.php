<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\TeamInvitationRepository as TeamInvitationRepositoryInterface;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function array_map;

final class TeamInvitationRepository extends GenericRepository implements TeamInvitationRepositoryInterface
{
    /** @return list<TeamInvitation> */
    #[Override]
    public function getRecentByTeam(TeamIdentifier $teamId, int $limit): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::TEAM_INVITATION))
            ->where($qb->expr()->eq('team_id', ':team_id'))
            ->setParameter('team_id', $teamId->toString())
            ->orderBy('invited_at', 'DESC')
            ->setMaxResults($limit);

        try {
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return array_map(fn (array $row) => $this->map($row, TeamInvitation::class), $rows);
        } catch (MappingError | Exception | JsonException) {
            return [];
        }
    }

    #[Override]
    public function getByToken(string $token): TeamInvitation
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::TEAM_INVITATION))
            ->where($qb->expr()->eq('token', ':token'))
            ->setParameter('token', $token);

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromField('token', EntityType::TEAM_INVITATION->value, $token);
            }

            return $this->map($row, TeamInvitation::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(TeamInvitation::class, $e);
        }
    }
}
