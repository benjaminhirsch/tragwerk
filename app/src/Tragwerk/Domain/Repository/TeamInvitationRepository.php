<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;

interface TeamInvitationRepository
{
    /**
     * @throws EntityNotFound
     * @throws EntityCreationFailed
     */
    public function getByToken(string $token): TeamInvitation;

    /** @throws EntityCreationFailed */
    public function create(TeamInvitation $invitation): void;

    /** @throws EntityDeletionFailed */
    public function delete(TeamInvitationIdentifier $id): void;
}
