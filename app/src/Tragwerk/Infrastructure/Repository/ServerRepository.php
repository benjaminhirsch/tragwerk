<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\ServerRepository as ServerRepositoryInterface;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function implode;

final class ServerRepository extends GenericRepository implements ServerRepositoryInterface
{
    #[Override]
    public function existsByHost(string $host, ServerIdentifier|null $excludeId = null): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(EntityHelper::getDbTableName(EntityType::SERVER))
            ->where($qb->expr()->eq('host', ':host'))
            ->setParameter('host', $host)
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere($qb->expr()->neq('id', ':exclude_id'))
                ->setParameter('exclude_id', $excludeId->toString());
        }

        try {
            return $qb->executeQuery()->fetchOne() !== false;
        } catch (Exception) {
            return false;
        }
    }

    #[Override]
    public function isCredentialAssigned(CredentialIdentifier $id): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(EntityHelper::getDbTableName(EntityType::SERVER))
            ->where($qb->expr()->eq('credential_id', ':id'))
            ->setParameter('id', $id->toString())
            ->setMaxResults(1);

        try {
            return $qb->executeQuery()->fetchOne() !== false;
        } catch (Exception) {
            return false;
        }
    }

    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
        ProjectIdentifier|null $projectId = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::SERVER));

        if ($ids !== null) {
            $qb->andWhere($qb->expr()->in('id', ':ids'));
            $qb->setParameter('ids', $ids);
        }

        if ($names !== null) {
            $qb->andWhere($qb->expr()->in('name', ':names'));
            $qb->setParameter('emails', implode(',', $names));
        }

        if ($projectId !== null) {
            $qb->andWhere($qb->expr()->eq('project_id', ':project_id'));
            $qb->setParameter('project_id', $projectId->toString());
        }

        try {
            foreach ($qb->addOrderBy('created_at', 'DESC')->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Server::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Server::class, $e);
        }
    }

    #[Override]
    public function updateVersions(
        ServerIdentifier $id,
        string|null $dockerVersion,
        string|null $dockerComposeVersion,
    ): void {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(EntityHelper::getDbTableName(EntityType::SERVER))
            ->set('docker_version', ':docker_version')
            ->set('docker_compose_version', ':docker_compose_version')
            ->where($qb->expr()->eq('id', ':id'))
            ->setParameter('docker_version', $dockerVersion)
            ->setParameter('docker_compose_version', $dockerComposeVersion)
            ->setParameter('id', $id->toString());

        try {
            $qb->executeStatement();
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }
}
