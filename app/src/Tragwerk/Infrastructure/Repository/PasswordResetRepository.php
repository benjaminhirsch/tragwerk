<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\PasswordReset;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\PasswordResetRepository as PasswordResetRepositoryInterface;
use Tragwerk\Domain\ValueObject\PasswordResetIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class PasswordResetRepository extends GenericRepository implements PasswordResetRepositoryInterface
{
    #[Override]
    public function getByToken(string $token): PasswordReset
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::PASSWORD_RESET))
            ->where($qb->expr()->eq('token', ':token'))
            ->setParameter('token', $token);

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromField('token', EntityType::PASSWORD_RESET->value, $token);
            }

            return $this->map($row, PasswordReset::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(PasswordReset::class, $e);
        }
    }

    #[Override]
    public function markUsed(PasswordResetIdentifier $id): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE password_resets SET used_at = NOW() WHERE id = :id',
                ['id' => $id->toString()],
            );
        } catch (Exception) {
        }
    }
}
