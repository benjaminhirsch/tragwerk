<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface RecoveryCodeRepository
{
    /** @throws EntityCreationFailed */
    public function create(RecoveryCode $entity): void;

    /** @return Generator<RecoveryCode> Unused codes for the user. */
    public function getActiveByUserId(UserIdentifier $userId): Generator;

    /** @return Generator<RecoveryCode> All codes for the user, used and unused. */
    public function getAllByUserId(UserIdentifier $userId): Generator;

    public function markUsed(RecoveryCodeIdentifier $id): void;

    public function deleteByUserId(UserIdentifier $userId): void;
}
