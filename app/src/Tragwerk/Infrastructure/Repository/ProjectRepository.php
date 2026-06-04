<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\ProjectRepository as ProjectRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function is_int;
use function is_string;

final class ProjectRepository extends GenericRepository implements ProjectRepositoryInterface
{
    #[Override]
    public function isServerInUse(ServerIdentifier $serverId, ProjectIdentifier|null $excludeProjectId = null): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from(EntityHelper::getDbTableName(EntityType::PROJECT))
            ->where($qb->expr()->eq('server_id', ':server_id'))
            ->setParameter('server_id', $serverId->toString());

        if ($excludeProjectId !== null) {
            $qb->andWhere($qb->expr()->neq('id', ':exclude_id'))
                ->setParameter('exclude_id', $excludeProjectId->toString());
        }

        $result = $qb->executeQuery()->fetchOne();

        return (is_string($result) || is_int($result)) && (int) $result > 0;
    }

    #[Override]
    public function getAll(TeamIdentifier|null $teamId = null): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::PROJECT));

        if ($teamId !== null) {
            $qb->andWhere($qb->expr()->eq('team_id', ':team_id'));
            $qb->setParameter('team_id', $teamId->toString());
        }

        try {
            foreach ($qb->addOrderBy('created_at', 'DESC')->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Project::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Project::class, $e);
        }
    }
}
