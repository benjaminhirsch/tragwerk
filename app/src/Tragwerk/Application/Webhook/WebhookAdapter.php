<?php

declare(strict_types=1);

namespace Tragwerk\Application\Webhook;

use Psr\Http\Message\ServerRequestInterface;

interface WebhookAdapter
{
    public function verify(ServerRequestInterface $request, string $secret): bool;

    public function extractPushPayload(ServerRequestInterface $request): PushPayload|null;
}
