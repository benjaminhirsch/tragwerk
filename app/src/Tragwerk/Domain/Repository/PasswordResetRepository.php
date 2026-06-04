<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\PasswordReset;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\PasswordResetIdentifier;

interface PasswordResetRepository
{
    /** @throws EntityCreationFailed */
    public function create(PasswordReset $entity): void;

    /** @throws EntityNotFound */
    public function getByToken(string $token): PasswordReset;

    public function markUsed(PasswordResetIdentifier $id): void;
}
