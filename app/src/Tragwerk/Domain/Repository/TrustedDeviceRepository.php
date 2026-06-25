<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\TrustedDevice;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\ValueObject\TrustedDeviceIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface TrustedDeviceRepository
{
    /** @throws EntityCreationFailed */
    public function create(TrustedDevice $entity): void;

    /** Returns a non-expired trusted device matching the token hash for the user, or null. */
    public function findValidByTokenHash(string $tokenHash, UserIdentifier $userId): TrustedDevice|null;

    public function touch(TrustedDeviceIdentifier $id): void;

    /** Deletes all expired trusted devices and returns the number of rows removed. */
    public function deleteExpired(): int;

    public function deleteByUserId(UserIdentifier $userId): void;
}
