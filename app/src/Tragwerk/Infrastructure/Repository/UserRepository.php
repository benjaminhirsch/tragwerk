<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Normalizer\Format;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Generator;
use JsonException;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\UserInterface;
use Override;
use SensitiveParameter;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Enum\Locale;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\Repository\UserRepository as UserRepositoryInterface;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_map;
use function assert;
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

        $details['abbreviation'] = $user->abbreviation()->forString();
        $details['oklch']        = $user->abbreviation()->oklch();

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
            $qb->setParameter(
                'ids',
                array_map(static fn (UserIdentifier $id): string => $id->toString(), $ids),
                ArrayParameterType::STRING,
            );
        }

        if ($emails !== null) {
            $qb->andWhere($qb->expr()->in('email', ':emails'));
            $qb->setParameter('emails', $emails, ArrayParameterType::STRING);
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

    public function getLastActiveTeamId(UserIdentifier $userId): TeamIdentifier|null
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT last_active_team_id FROM users WHERE id = :id',
                ['id' => $userId->toString()],
            );

            if (! is_string($value) || ! TeamIdentifier::isValid($value)) {
                return null;
            }

            return TeamIdentifier::fromString($value);
        } catch (Exception) {
            return null;
        }
    }

    public function setLastActiveTeam(UserIdentifier $userId, TeamIdentifier $teamId): void
    {
        try {
            $this->connection->update(
                'users',
                ['last_active_team_id' => $teamId->toString()],
                ['id' => $userId->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($userId, $e);
        }
    }

    public function confirm(UserIdentifier $id): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE users SET confirmed_at = NOW() WHERE id = :id',
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    public function updatePassword(UserIdentifier $id, string $passwordHash): void
    {
        try {
            $this->connection->update(
                'users',
                ['password' => $passwordHash],
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    public function updateProfile(UserIdentifier $id, string $firstname, string $lastname): void
    {
        try {
            $this->connection->update(
                'users',
                ['firstname' => $firstname, 'lastname' => $lastname, 'updated_at' => $this->now()],
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    public function updateEmail(UserIdentifier $id, string $email): void
    {
        try {
            $this->connection->update(
                'users',
                ['email' => $email, 'updated_at' => $this->now()],
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    public function updateLocale(UserIdentifier $id, Locale|null $locale): void
    {
        try {
            $this->connection->update(
                'users',
                ['locale' => $locale?->value, 'updated_at' => $this->now()],
                ['id' => $id->toString()],
            );
        } catch (Exception $e) {
            throw EntityUpdateFailed::create($id, $e);
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s.u');
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
