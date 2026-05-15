<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Normalizer\Format;
use Doctrine\DBAL\Exception;
use Generator;
use JsonException;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\UserInterface;
use Override;
use SensitiveParameter;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository as UserRepositoryInterface;

use function assert;
use function implode;
use function is_array;
use function is_string;
use function password_verify;

final class UserRepository extends GenericRepository implements UserRepositoryInterface
{
    #[Override]
    public function authenticate(string $credential, #[SensitiveParameter]
    string|null $password = null,): UserInterface|null
    {
        $user = $this->getAll(emails: [$credential])->current();
        assert($user instanceof User || $user === null);

        if (! $user instanceof User) {
            return null;
        }

        $details = $this->normalizerBuilder->normalizer(Format::array())->normalize($user);
        assert(is_array($details) && isset($details['password']) && is_string($details['password']));

        if (password_verify($password ?? '', $details['password'])) {
            unset($details['password']);

            // @phpstan-ignore argument.type
            return new DefaultUser($user->id->toString(), details: $details);
        }

        return null;
    }

    #[Override]
    public function getAll(
        array|null $ids = null,
        array|null $emails = null,
    ): Generator {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')->from('users');

        if ($ids !== null) {
            $qb->andWhere($qb->expr()->in('id', ':ids'));
            $qb->setParameter('ids', $ids);
        }

        if ($emails !== null) {
            $qb->andWhere($qb->expr()->in('email', ':emails'));
            $qb->setParameter('emails', implode(',', $emails));
        }

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $character) {
                yield $this->map($character, User::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(User::class, $e);
        }
    }

    public function searchByEmail(string $email): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('users')
            ->where($qb->expr()->like('email', ':email'))
        ->setParameter('email', $email . '%');

        try {
            foreach ($qb->executeQuery()->iterateAssociative() as $character) {
                yield $this->map($character, User::class);
            }
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(User::class, $e);
        }
    }

    public function getByEmail(string $email): User
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('users')
            ->where($qb->expr()->eq('email', ':email'))
        ->setParameter('email', $email);

        try {
            $data = $qb->fetchAssociative();

            if ($data === false) {
                throw EntityNotFound::fromField('email', EntityType::USER->value, $email);
            }

            return $this->map($data, User::class);
        } catch (MappingError | Exception | JsonException $e) {
            throw EntityHydrationFailed::create(User::class, $e);
        }
    }
}
