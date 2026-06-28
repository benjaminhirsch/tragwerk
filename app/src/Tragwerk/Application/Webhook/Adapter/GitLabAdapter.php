<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook\Adapter;

use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Application\Webhook\PushPayload;
use Tragwerk\Application\Webhook\WebhookAdapter;

use function hash_equals;
use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

final readonly class GitLabAdapter implements WebhookAdapter
{
    private const string ZERO_SHA = '0000000000000000000000000000000000000000';

    public function verify(ServerRequestInterface $request, string $secret): bool
    {
        $header = $request->getHeaderLine('X-Gitlab-Token');

        return hash_equals($secret, $header);
    }

    public function extractPushPayload(ServerRequestInterface $request): PushPayload|null
    {
        $body = $request->getParsedBody();

        if (! is_array($body)) {
            return null;
        }

        $ref = $body['ref'] ?? null;
        $sha = $body['after'] ?? null;

        if (! is_string($ref) || ! str_starts_with($ref, 'refs/heads/')) {
            return null;
        }

        if (! is_string($sha) || $sha === '') {
            return null;
        }

        if ($sha === self::ZERO_SHA) {
            return new PushPayload(branch: substr($ref, 11), commitSha: '', deleted: true);
        }

        return new PushPayload(
            branch:    substr($ref, 11),
            commitSha: $sha,
        );
    }
}
