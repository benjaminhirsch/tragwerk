<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\CredentialRepository as CredentialRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class CredentialRepository extends GenericRepository implements CredentialRepositoryInterface
{
    #[Override]
    public function getAll(
        array|null $ids = null,
        ProjectIdentifier|null $projectId = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::CREDENTIAL));

        if ($ids !== null) {
            $qb->andWhere($qb->expr()->in('id', ':ids'));
            $qb->setParameter('ids', $ids);
        }

        if ($projectId !== null) {
            $qb->andWhere($qb->expr()->eq('project_id', ':project_id'));
            $qb->setParameter('project_id', $projectId->toString());
        }

        try {
            foreach ($qb->addOrderBy('created_at', 'DESC')->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Credential::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Credential::class, $e);
        }
    }
}
