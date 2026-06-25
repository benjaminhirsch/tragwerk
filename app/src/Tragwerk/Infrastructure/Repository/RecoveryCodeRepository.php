<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\RecoveryCodeRepository as RecoveryCodeRepositoryInterface;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class RecoveryCodeRepository extends GenericRepository implements RecoveryCodeRepositoryInterface
{
    #[Override]
    public function getActiveByUserId(UserIdentifier $userId): Generator
    {
        yield from $this->query($userId, activeOnly: true);
    }

    #[Override]
    public function getAllByUserId(UserIdentifier $userId): Generator
    {
        yield from $this->query($userId, activeOnly: false);
    }

    #[Override]
    public function markUsed(RecoveryCodeIdentifier $id): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE user_recovery_codes SET used_at = NOW() WHERE id = :id',
                ['id' => $id->toString()],
            );
        } catch (Exception) {
        }
    }

    #[Override]
    public function deleteByUserId(UserIdentifier $userId): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM user_recovery_codes WHERE user_id = :id',
                ['id' => $userId->toString()],
            );
        } catch (Exception) {
        }
    }

    /** @return Generator<RecoveryCode> */
    private function query(UserIdentifier $userId, bool $activeOnly): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::RECOVERY_CODE))
            ->where($qb->expr()->eq('user_id', ':user_id'))
            ->setParameter('user_id', $userId->toString())
            ->addOrderBy('created_at', 'ASC');

        if ($activeOnly) {
            $qb->andWhere($qb->expr()->isNull('used_at'));
        }

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, RecoveryCode::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(RecoveryCode::class, $e);
        }
    }
}
