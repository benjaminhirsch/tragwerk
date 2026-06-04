<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;

interface EmailConfirmationRepository
{
    /** @throws EntityCreationFailed */
    public function create(EmailConfirmation $entity): void;

    /** @throws EntityNotFound */
    public function getByToken(string $token): EmailConfirmation;
}
