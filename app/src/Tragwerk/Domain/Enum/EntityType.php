<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

use Override;
use Tragwerk\Domain\Entity;

use function _;

enum EntityType: string implements Translatable
{
    case USER            = 'USER';
    case SERVER          = 'SERVER';
    case TEAM            = 'TEAM';
    case TEAM_INVITATION = 'TEAM_INVITATION';
    case CREDENTIAL      = 'CREDENTIAL';
    case SETUP_JOB       = 'SETUP_JOB';
    case PROJECT         = 'PROJECT';
    case SSH_KEY         = 'SSH_KEY';
    case BUILD_LOG       = 'BUILD_LOG';
    case DEPLOY_JOB      = 'DEPLOY_JOB';
    case DOMAIN          = 'DOMAIN';

    /** @phpstan-pure  */
    #[Override]
    public function translatableName(): string
    {
        return match ($this) {
            self::USER            => _('User'),
            self::SERVER          => _('Server'),
            self::TEAM            => _('Team'),
            self::TEAM_INVITATION => _('Team Invitation'),
            self::CREDENTIAL      => _('Credential'),
            self::SETUP_JOB       => _('Setup Job'),
            self::PROJECT         => _('Project'),
            self::SSH_KEY         => _('SSH Key'),
            self::BUILD_LOG       => _('Build Log'),
            self::DEPLOY_JOB      => _('Deploy Job'),
            self::DOMAIN          => _('Domain'),
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
            self::USER            => Entity\User::class,
            self::SERVER          => Entity\Server::class,
            self::TEAM            => Entity\Team::class,
            self::TEAM_INVITATION => Entity\TeamInvitation::class,
            self::CREDENTIAL      => Entity\Credential::class,
            self::SETUP_JOB       => Entity\SetupJob::class,
            self::PROJECT         => Entity\Project::class,
            self::SSH_KEY         => Entity\SshKey::class,
            self::BUILD_LOG       => Entity\BuildLog::class,
            self::DEPLOY_JOB      => Entity\DeployJob::class,
            self::DOMAIN          => Entity\Domain::class,
        };
    }
}
