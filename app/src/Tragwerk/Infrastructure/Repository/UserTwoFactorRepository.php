<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\UserTwoFactor;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\UserTwoFactorRepository as UserTwoFactorRepositoryInterface;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class UserTwoFactorRepository extends GenericRepository implements UserTwoFactorRepositoryInterface
{
    #[Override]
    public function findByUserId(UserIdentifier $userId): UserTwoFactor|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::USER_TWO_FACTOR))
            ->where($qb->expr()->eq('user_id', ':user_id'))
            ->setParameter('user_id', $userId->toString());

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                return null;
            }

            return $this->map($row, UserTwoFactor::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(UserTwoFactor::class, $e);
        }
    }

    #[Override]
    public function getByUserId(UserIdentifier $userId): UserTwoFactor
    {
        $entity = $this->findByUserId($userId);

        if ($entity === null) {
            throw EntityNotFound::fromField('user_id', EntityType::USER_TWO_FACTOR->value, $userId->toString());
        }

        return $entity;
    }

    #[Override]
    public function confirm(UserIdentifier $userId): void
    {
        try {
            $this->connection->transactional(function () use ($userId): void {
                $this->connection->executeStatement(
                    'UPDATE user_two_factor SET confirmed_at = NOW(), updated_at = NOW() WHERE user_id = :id',
                    ['id' => $userId->toString()],
                );
                $this->connection->executeStatement(
                    'UPDATE users SET two_factor_confirmed_at = NOW() WHERE id = :id',
                    ['id' => $userId->toString()],
                );
            });
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($userId, $e);
        }
    }

    #[Override]
    public function deleteByUserId(UserIdentifier $userId): void
    {
        try {
            $this->connection->transactional(function () use ($userId): void {
                $this->connection->executeStatement(
                    'DELETE FROM user_two_factor WHERE user_id = :id',
                    ['id' => $userId->toString()],
                );
                $this->connection->executeStatement(
                    'UPDATE users SET two_factor_confirmed_at = NULL WHERE id = :id',
                    ['id' => $userId->toString()],
                );
            });
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($userId, $e);
        }
    }
}
