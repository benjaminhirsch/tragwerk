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
use Tragwerk\Domain\Repository\ServerRepository as ServerRepositoryInterface;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function implode;

final class ServerRepository extends GenericRepository implements ServerRepositoryInterface
{
    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
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

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $character) {
                yield $this->map($character, Server::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Server::class, $e);
        }
    }
}
