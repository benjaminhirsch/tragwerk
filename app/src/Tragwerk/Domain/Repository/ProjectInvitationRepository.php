<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\ProjectInvitation;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\ProjectInvitationIdentifier;

interface ProjectInvitationRepository
{
    /**
     * @throws EntityNotFound
     * @throws EntityCreationFailed
     */
    public function getByToken(string $token): ProjectInvitation;

    /** @throws EntityCreationFailed */
    public function create(ProjectInvitation $invitation): void;

    /** @throws EntityDeletionFailed */
    public function delete(ProjectInvitationIdentifier $id): void;
}
