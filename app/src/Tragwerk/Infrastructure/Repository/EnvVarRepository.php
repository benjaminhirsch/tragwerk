<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Generator;
use Override;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\EnvVarRepository as EnvVarRepositoryInterface;
use Tragwerk\Domain\ValueObject\EntityIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function assert;

final class EnvVarRepository extends GenericRepository implements EnvVarRepositoryInterface
{
    #[Override]
    public function getById(EntityIdentifier $id): EnvVar
    {
        $entity = parent::getById($id);
        assert($entity instanceof EnvVar);

        return $entity;
    }

    #[Override]
    public function findByBranch(ProjectIdentifier $projectId, string $branch): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::ENV_VAR))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->orderBy('key', 'ASC');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, EnvVar::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(EnvVar::class, $e);
        }
    }

    #[Override]
    public function findInheritedFromAncestors(ProjectIdentifier $projectId, array $ancestorBranches): Generator
    {
        if ($ancestorBranches === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::ENV_VAR))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('is_inherited', ':inherited'))
            ->andWhere($qb->expr()->in('branch', ':branches'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('inherited', true, 'boolean')
            ->setParameter('branches', $ancestorBranches, Types::SIMPLE_ARRAY)
            ->orderBy('key', 'ASC');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, EnvVar::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(EnvVar::class, $e);
        }
    }
}
