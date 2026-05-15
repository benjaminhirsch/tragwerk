<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Mezzio\Authentication\UserRepositoryInterface;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface UserRepository extends UserRepositoryInterface
{
    /**
     * @return User
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(UserIdentifier $id): Entity;

    /**
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getByEmail(string $email): User;

    /** @throws EntityCreationFailed */
    public function create(User $entity): void;

    /**
     * @param UserIdentifier[]|null $ids
     * @param string[]|null         $emails
     */
    public function getAll(
        array|null $ids = null,
        array|null $emails = null,
    ): Generator;

    /** @return Generator<User> */
    public function searchByEmail(string $email): Generator;
}
