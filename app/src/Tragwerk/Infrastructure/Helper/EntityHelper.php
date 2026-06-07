<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Helper;

use Tragwerk\Domain\Enum\EntityType;

final readonly class EntityHelper
{
    public static function getDbTableName(EntityType $entityType): string
    {
        return match ($entityType) {
            EntityType::USER            => 'users',
            EntityType::SERVER          => 'servers',
            EntityType::TEAM            => 'teams',
            EntityType::TEAM_INVITATION => 'team_invitations',
            EntityType::CREDENTIAL      => 'credentials',
            EntityType::SETUP_JOB       => 'setup_jobs',
            EntityType::PROJECT       => 'projects',
            EntityType::SSH_KEY       => 'ssh_keys',
            EntityType::BUILD_LOG     => 'build_logs',
            EntityType::DEPLOY_JOB    => 'deploy_jobs',
            EntityType::DOMAIN        => 'project_domains',
            EntityType::REGISTRY           => 'registries',
            EntityType::EMAIL_CONFIRMATION => 'email_confirmations',
            EntityType::PASSWORD_RESET     => 'password_resets',
            EntityType::WEBHOOK_INTEGRATION => 'project_webhooks',
        };
    }
}
