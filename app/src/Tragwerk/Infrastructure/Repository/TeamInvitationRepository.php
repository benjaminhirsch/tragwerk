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
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class TeamInvitationRepository extends GenericRepository implements TeamInvitationRepositoryInterface
{
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
