<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface TeamRepository
{
    /**
     * @return Team
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(TeamIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Team $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Team $entity): void;

    /**
     * @param TeamIdentifier[]|null $ids
     * @param string[]|null         $names
     * @param UserIdentifier[]|null $ownerIds
     */
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
        array|null $ownerIds = null,
    ): Generator;

    /** @throws EntityDeletionFailed */
    public function delete(TeamIdentifier $id): void;

    /**
     * @param UserIdentifier[] $userIds
     *
     * @throws EntityCreationFailed
     */
    public function assignUsers(TeamIdentifier $teamId, array $userIds): void;

    /** @return Generator<Team> */
    public function getByUserId(UserIdentifier $userId): Generator;

    /** @return Generator<User> */
    public function getUsersByTeamId(TeamIdentifier $teamId): Generator;

    /** @throws EntityDeletionFailed */
    public function removeUser(TeamIdentifier $teamId, UserIdentifier $userId): void;
}
