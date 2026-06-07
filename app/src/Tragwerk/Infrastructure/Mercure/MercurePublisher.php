<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Mercure;

use function base64_encode;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function hash_hmac;
use function json_encode;
use function rawurlencode;
use function rtrim;
use function str_replace;

use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;

final class MercurePublisher
{
    private string $publisherJwt;

    /**
     * @param non-empty-string $hubUrl
     * @param non-empty-string $topicBase
     */
    public function __construct(
        private readonly string $hubUrl,
        private readonly string $topicBase,
        string $publisherJwtSecret,
    ) {
        $this->publisherJwt = $this->buildJwt($publisherJwtSecret);
    }

    public function topic(string $path): string
    {
        return $this->topicBase . $path;
    }

    /** @param array<string, mixed> $data */
    public function publish(string $topic, array $data): void
    {
        $ch = curl_init();
        if ($ch === false) {
            return;
        }

        curl_setopt($ch, CURLOPT_URL, $this->hubUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        $body = 'topic=' . rawurlencode($topic) . '&data=' . rawurlencode((string) json_encode($data));

        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->publisherJwt,
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        curl_exec($ch);
    }

    private function buildJwt(string $secret): string
    {
        $header  = $this->base64url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64url((string) json_encode(['mercure' => ['publish' => ['*']]]));
        $sig     = $this->base64url(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return $header . '.' . $payload . '.' . $sig;
    }

    private function base64url(string $data): string
    {
        return rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($data)), '=');
    }
}
