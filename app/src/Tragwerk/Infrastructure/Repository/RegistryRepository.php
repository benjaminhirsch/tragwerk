<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\RegistryRepository as RegistryRepositoryInterface;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function assert;
use function is_int;
use function is_string;

final class RegistryRepository extends GenericRepository implements RegistryRepositoryInterface
{
    #[Override]
    public function getAll(TeamIdentifier|null $teamId = null): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::REGISTRY));

        if ($teamId !== null) {
            $qb->andWhere($qb->expr()->eq('team_id', ':team_id'));
            $qb->setParameter('team_id', $teamId->toString());
        }

        try {
            foreach ($qb->addOrderBy('name', 'ASC')->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Registry::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Registry::class, $e);
        }
    }

    #[Override]
    public function isAssignedToProject(RegistryIdentifier $id): bool
    {
        try {
            $result = $this->connection->fetchOne(
                'SELECT count(*) FROM projects WHERE registry_id = :id',
                ['id' => $id->toString()],
            );
            assert(is_int($result) || is_string($result) || $result === false);
            $count = (int) ($result !== false ? $result : 0);

            return $count > 0;
        } catch (Exception) {
            return false;
        }
    }
}
