<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum GitForge: string
{
    case GITHUB    = 'github';
    case GITLAB    = 'gitlab';
    case FORGEJO   = 'forgejo';
    case GITEA     = 'gitea';
    case CODEBERG  = 'codeberg';
    case BITBUCKET = 'bitbucket';

    public static function tryFromRouteSlug(string $slug): self|null
    {
        return self::tryFrom($slug);
    }

    public function label(): string
    {
        return match ($this) {
            self::GITHUB    => 'GitHub',
            self::GITLAB    => 'GitLab',
            self::FORGEJO   => 'Forgejo',
            self::GITEA     => 'Gitea',
            self::CODEBERG  => 'Codeberg',
            self::BITBUCKET => 'Bitbucket',
        };
    }
}
