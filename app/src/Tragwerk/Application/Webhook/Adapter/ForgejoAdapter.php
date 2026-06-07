<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook\Adapter;

use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Application\Webhook\PushPayload;
use Tragwerk\Application\Webhook\WebhookAdapter;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

/** Covers Forgejo, Gitea, and Codeberg (all use the same webhook format). */
final readonly class ForgejoAdapter implements WebhookAdapter
{
    private const string ZERO_SHA = '0000000000000000000000000000000000000000';

    public function verify(ServerRequestInterface $request, string $secret): bool
    {
        $header = $request->getHeaderLine('X-Gitea-Signature');

        if ($header === '') {
            return false;
        }

        $rawBody = (string) $request->getBody();
        $request->getBody()->rewind();

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
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

        if (! is_string($sha) || $sha === '' || $sha === self::ZERO_SHA) {
            return null;
        }

        return new PushPayload(
            branch:    substr($ref, 11),
            commitSha: $sha,
        );
    }
}
