<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\TrustedDevice;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\TrustedDeviceRepository as TrustedDeviceRepositoryInterface;
use Tragwerk\Domain\ValueObject\TrustedDeviceIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function is_int;

final class TrustedDeviceRepository extends GenericRepository implements TrustedDeviceRepositoryInterface
{
    #[Override]
    public function findValidByTokenHash(string $tokenHash, UserIdentifier $userId): TrustedDevice|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::TRUSTED_DEVICE))
            ->where($qb->expr()->eq('token_hash', ':token_hash'))
            ->andWhere($qb->expr()->eq('user_id', ':user_id'))
            ->andWhere('expires_at > NOW()')
            ->setParameter('token_hash', $tokenHash)
            ->setParameter('user_id', $userId->toString());

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                return null;
            }

            return $this->map($row, TrustedDevice::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(TrustedDevice::class, $e);
        }
    }

    #[Override]
    public function touch(TrustedDeviceIdentifier $id): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE user_trusted_devices SET last_used_at = NOW() WHERE id = :id',
                ['id' => $id->toString()],
            );
        } catch (Exception) {
        }
    }

    #[Override]
    public function deleteExpired(): int
    {
        try {
            $deleted = $this->connection->executeStatement(
                'DELETE FROM user_trusted_devices WHERE expires_at < NOW()',
            );

            return is_int($deleted) ? $deleted : 0;
        } catch (Exception) {
            return 0;
        }
    }

    #[Override]
    public function deleteByUserId(UserIdentifier $userId): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM user_trusted_devices WHERE user_id = :id',
                ['id' => $userId->toString()],
            );
        } catch (Exception) {
        }
    }
}
