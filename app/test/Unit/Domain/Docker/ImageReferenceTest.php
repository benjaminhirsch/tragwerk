<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Docker\ImageReference;

use function strlen;

final class ImageReferenceTest extends TestCase
{
    #[Test]
    public function fingerprintIsDeterministicForSameInputs(): void
    {
        $a = ImageReference::fingerprint('abc123', 'FROM php', 'entrypoint', 'caddy');
        $b = ImageReference::fingerprint('abc123', 'FROM php', 'entrypoint', 'caddy');

        self::assertSame($a, $b);
        self::assertSame(16, strlen($a));
    }

    #[Test]
    public function fingerprintChangesWhenArtifactChanges(): void
    {
        $base    = ImageReference::fingerprint('abc123', 'FROM php:8.5', 'entrypoint');
        $changed = ImageReference::fingerprint('abc123', 'FROM php:8.4', 'entrypoint');

        self::assertNotSame($base, $changed);
    }

    #[Test]
    public function fingerprintChangesWhenCommitChanges(): void
    {
        $base    = ImageReference::fingerprint('abc123', 'FROM php');
        $changed = ImageReference::fingerprint('def456', 'FROM php');

        self::assertNotSame($base, $changed);
    }

    #[Test]
    public function tagFollowsExpectedFormat(): void
    {
        $tag = ImageReference::tag('registry.example.com', 'my-repo', 'web', 'main', 'deadbeefdeadbeef');

        self::assertSame('registry.example.com/my-repo:web-main-deadbeefdeadbeef', $tag);
    }
}
