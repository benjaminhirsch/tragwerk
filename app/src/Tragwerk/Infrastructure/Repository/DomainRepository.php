<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Override;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\DomainRepository as DomainRepositoryInterface;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\EntityIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function assert;

final class DomainRepository extends GenericRepository implements DomainRepositoryInterface
{
    #[Override]
    public function getById(EntityIdentifier $id): Domain
    {
        $entity = parent::getById($id);
        assert($entity instanceof Domain);

        return $entity;
    }

    #[Override]
    public function findByProject(ProjectIdentifier $projectId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::DOMAIN))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->setParameter('project_id', $projectId->toString())
            ->orderBy('is_primary', 'DESC')
            ->addOrderBy('created_at', 'ASC');

        try {
            $domains = [];
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                $domains[] = $this->map($row, Domain::class);
            }

            return $domains;
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Domain::class, $e);
        }
    }

    #[Override]
    public function findByEnvironment(ProjectIdentifier $projectId, string $branch): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::DOMAIN))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('branch', ':branch'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('branch', $branch)
            ->orderBy('is_primary', 'DESC')
            ->addOrderBy('created_at', 'ASC');

        try {
            $domains = [];
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                $domains[] = $this->map($row, Domain::class);
            }

            return $domains;
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Domain::class, $e);
        }
    }

    #[Override]
    public function clearPrimary(ProjectIdentifier $projectId, string $branch): void
    {
        $qb = $this->connection->createQueryBuilder();
        try {
            $qb->update(EntityHelper::getDbTableName(EntityType::DOMAIN))
                ->set('is_primary', ':false')
                ->where($qb->expr()->eq('project_id', ':project_id'))
                ->andWhere($qb->expr()->eq('branch', ':branch'))
                ->setParameter('false', false, 'boolean')
                ->setParameter('project_id', $projectId->toString())
                ->setParameter('branch', $branch)
                ->executeStatement();
        } catch (Exception $e) {
            throw EntityUpdateFailed::create(DomainIdentifier::nil(), $e);
        }
    }

    #[Override]
    public function setPrimary(DomainIdentifier $id): void
    {
        $qb = $this->connection->createQueryBuilder();
        try {
            $qb->update(EntityHelper::getDbTableName(EntityType::DOMAIN))
                ->set('is_primary', ':true')
                ->where($qb->expr()->eq('id', ':id'))
                ->setParameter('true', true, 'boolean')
                ->setParameter('id', $id->toString())
                ->executeStatement();
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }
}
