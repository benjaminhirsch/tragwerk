<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

use Override;
use Tragwerk\Domain\Entity;

use function _;

enum EntityType: string implements Translatable
{
    case USER               = 'USER';
    case SERVER             = 'SERVER';
    case PROJECT            = 'PROJECT';
    case PROJECT_INVITATION = 'PROJECT_INVITATION';
    case CREDENTIAL         = 'CREDENTIAL';
    case SETUP_JOB          = 'SETUP_JOB';

    /** @phpstan-pure  */
    #[Override]
    public function translatableName(): string
    {
        return match ($this) {
            self::USER               => _('User'),
            self::SERVER             => _('Server'),
            self::PROJECT            => _('Project'),
            self::PROJECT_INVITATION => _('Project Invitation'),
            self::CREDENTIAL         => _('Credential'),
            self::SETUP_JOB          => _('Setup Job'),
        };
    }

    /**
     * @return class-string
     *
     * @phpstan-pure
     */
    public function getEntityClassName(): string
    {
        return match ($this) {
            self::USER               => Entity\User::class,
            self::SERVER             => Entity\Server::class,
            self::PROJECT            => Entity\Project::class,
            self::PROJECT_INVITATION => Entity\ProjectInvitation::class,
            self::CREDENTIAL         => Entity\Credential::class,
            self::SETUP_JOB          => Entity\SetupJob::class,
        };
    }
}
