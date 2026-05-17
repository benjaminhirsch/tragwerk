<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\ProjectInvitation;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\ProjectInvitationRepository as ProjectInvitationRepositoryInterface;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class ProjectInvitationRepository extends GenericRepository implements ProjectInvitationRepositoryInterface
{
    #[Override]
    public function getByToken(string $token): ProjectInvitation
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::PROJECT_INVITATION))
            ->where($qb->expr()->eq('token', ':token'))
            ->setParameter('token', $token);

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromField('token', EntityType::PROJECT_INVITATION->value, $token);
            }

            return $this->map($row, ProjectInvitation::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(ProjectInvitation::class, $e);
        }
    }
}
