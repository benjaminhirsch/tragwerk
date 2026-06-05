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
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function is_int;
use function is_string;

final class ProjectRepository extends GenericRepository implements ProjectRepositoryInterface
{
    #[Override]
    public function countProjectsByServer(TeamIdentifier $teamId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('server_id', 'COUNT(*) AS cnt')
            ->from(EntityHelper::getDbTableName(EntityType::PROJECT))
            ->where($qb->expr()->eq('team_id', ':team_id'))
            ->setParameter('team_id', $teamId->toString())
            ->groupBy('server_id');

        $counts = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            if (! is_string($row['server_id'])) {
                continue;
            }

            $cnt                       = $row['cnt'];
            $counts[$row['server_id']] = is_int($cnt) ? $cnt : (int) (is_string($cnt) ? $cnt : 0);
        }

        return $counts;
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
