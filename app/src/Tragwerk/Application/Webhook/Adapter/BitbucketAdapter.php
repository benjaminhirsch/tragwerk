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

final readonly class BitbucketAdapter implements WebhookAdapter
{
    public function verify(ServerRequestInterface $request, string $secret): bool
    {
        $header = $request->getHeaderLine('X-Hub-Signature');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $rawBody = (string) $request->getBody();
        $request->getBody()->rewind();

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
    }

    public function extractPushPayload(ServerRequestInterface $request): PushPayload|null
    {
        $body = $request->getParsedBody();

        if (! is_array($body)) {
            return null;
        }

        $changes = $body['push']['changes'] ?? null;

        if (! is_array($changes) || $changes === []) {
            return null;
        }

        $new = $changes[0]['new'] ?? null;

        if (! is_array($new) || ($new['type'] ?? null) !== 'branch') {
            return null;
        }

        $branch = $new['name'] ?? null;
        $sha    = $new['target']['hash'] ?? null;

        if (! is_string($branch) || $branch === '' || ! is_string($sha) || $sha === '') {
            return null;
        }

        return new PushPayload(
            branch:    $branch,
            commitSha: $sha,
        );
    }
}
