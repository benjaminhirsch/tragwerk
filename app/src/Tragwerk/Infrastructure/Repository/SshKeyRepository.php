<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\SshKeyRepository as SshKeyRepositoryInterface;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final readonly class SshKeyRepository implements SshKeyRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private MapperBuilder $mapperBuilder,
    ) {
    }

    #[Override]
    public function create(SshKey $key): void
    {
        try {
            $this->connection->insert(EntityHelper::getDbTableName(EntityType::SSH_KEY), [
                'id'         => $key->id->toString(),
                'user_id'    => $key->userId->toString(),
                'name'       => $key->name,
                'public_key' => $key->publicKey,
                'created_at' => $key->createdAt->format('Y-m-d H:i:s.u'),
            ]);
        } catch (Exception $e) {
            throw EntityCreationFailed::create(SshKey::class, $key->id, $e);
        }
    }

    #[Override]
    public function delete(SshKeyIdentifier $id): void
    {
        try {
            $this->connection->delete(
                EntityHelper::getDbTableName(EntityType::SSH_KEY),
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityDeletionFailed::create($id, $e);
        }
    }

    #[Override]
    public function getByUserId(UserIdentifier $userId): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::SSH_KEY))
            ->where($qb->expr()->eq('user_id', ':user_id'))
            ->setParameter('user_id', $userId->toString())
            ->addOrderBy('created_at', 'DESC');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(SshKey::class, $e);
        }
    }

    #[Override]
    public function getAll(): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::SSH_KEY))
            ->addOrderBy('created_at', 'ASC');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(SshKey::class, $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): SshKey
    {
        return $this->mapperBuilder->mapper()->map(SshKey::class, $row);
    }
}
