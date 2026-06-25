<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\UserTwoFactor;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface UserTwoFactorRepository
{
    /** @throws EntityCreationFailed */
    public function create(UserTwoFactor $entity): void;

    public function findByUserId(UserIdentifier $userId): UserTwoFactor|null;

    /** @throws EntityNotFound */
    public function getByUserId(UserIdentifier $userId): UserTwoFactor;

    /** Marks the enrollment as confirmed and flags the user as two-factor enabled. */
    public function confirm(UserIdentifier $userId): void;

    /** Removes the secret for the user and clears the user's two-factor flag. */
    public function deleteByUserId(UserIdentifier $userId): void;
}
