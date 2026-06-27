<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use function hash;
use function implode;
use function substr;

/**
 * Builds deterministic docker image references for an application build.
 *
 * The fingerprint is derived from the commit plus the generated build artifacts (Dockerfile,
 * Caddyfile, entrypoint). Two builds with the same source and the same generated recipe produce
 * the same tag, which lets the deploy step reuse an already-pushed image instead of rebuilding.
 */
final readonly class ImageReference
{
    /**
     * A short, stable fingerprint over the build inputs. Changing the commit OR any generated
     * artifact (e.g. after a Tragwerk generator update) yields a different fingerprint.
     */
    public static function fingerprint(string $commitSha, string ...$artifactContents): string
    {
        return substr(hash('sha256', $commitSha . "\0" . implode("\0", $artifactContents)), 0, 16);
    }

    public static function tag(
        string $registryUrl,
        string $repository,
        string $appSlug,
        string $branchSlug,
        string $fingerprint,
    ): string {
        return $registryUrl . '/' . $repository . ':' . $appSlug . '-' . $branchSlug . '-' . $fingerprint;
    }
}
