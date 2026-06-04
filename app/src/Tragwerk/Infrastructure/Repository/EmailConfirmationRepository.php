<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use JsonException;
use Override;
use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\EmailConfirmationRepository as EmailConfirmationRepositoryInterface;
use Tragwerk\Infrastructure\Helper\EntityHelper;

final class EmailConfirmationRepository extends GenericRepository implements EmailConfirmationRepositoryInterface
{
    #[Override]
    public function getByToken(string $token): EmailConfirmation
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::EMAIL_CONFIRMATION))
            ->where($qb->expr()->eq('token', ':token'))
            ->setParameter('token', $token);

        try {
            $row = $qb->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromField('token', EntityType::EMAIL_CONFIRMATION->value, $token);
            }

            return $this->map($row, EmailConfirmation::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(EmailConfirmation::class, $e);
        }
    }
}
