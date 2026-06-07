<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook;

use Tragwerk\Application\Webhook\Adapter\BitbucketAdapter;
use Tragwerk\Application\Webhook\Adapter\ForgejoAdapter;
use Tragwerk\Application\Webhook\Adapter\GitHubAdapter;
use Tragwerk\Application\Webhook\Adapter\GitLabAdapter;
use Tragwerk\Domain\Enum\GitForge;

final readonly class AdapterRegistry
{
    public function __construct(
        private GitHubAdapter $github,
        private GitLabAdapter $gitlab,
        private ForgejoAdapter $forgejo,
        private BitbucketAdapter $bitbucket,
    ) {
    }

    public function get(GitForge $forge): WebhookAdapter
    {
        return match ($forge) {
            GitForge::GITHUB    => $this->github,
            GitForge::GITLAB    => $this->gitlab,
            GitForge::FORGEJO,
            GitForge::GITEA,
            GitForge::CODEBERG  => $this->forgejo,
            GitForge::BITBUCKET => $this->bitbucket,
        };
    }
}
