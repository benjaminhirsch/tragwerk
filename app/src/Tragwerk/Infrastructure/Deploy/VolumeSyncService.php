<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Deploy;

use Closure;
use phpseclib3\Net\SFTP;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Model\ProjectConfig;

use function basename;
use function escapeshellarg;
use function preg_replace;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Clones the data volumes of a parent branch environment into a child environment.
 *
 * For every volume the copy strategy is chosen per host filesystem:
 *  - reflink fast-path: if the Docker volume filesystem supports copy-on-write reflinks
 *    (btrfs / XFS-reflink / ZFS) the source is stopped briefly and cloned near-instantly.
 *  - rsync 2-pass fallback: pass 1 runs hot (source still serving), pass 2 copies only the
 *    delta while the source is stopped, yielding a consistent copy with minimal downtime.
 *
 * Downtime therefore no longer scales with the total volume size, only with the reflink
 * snapshot (constant) or the write delta accumulated during pass 1 (usually small).
 */
final class VolumeSyncService
{
    private const string IMAGE = 'alpine';

    /** @param Closure(string): void $log */
    public function syncVolumes(
        SFTP $sftp,
        string $projectId,
        string $branch,
        string $parentBranch,
        ProjectConfig $config,
        Closure $log,
    ): void {
        $parentSlug           = $this->slugify(basename($parentBranch));
        $branchSlug           = $this->slugify(basename($branch));
        $parentDir            = 'tragwerk/' . $projectId . '/' . $parentBranch;
        $childDir             = 'tragwerk/' . $projectId . '/' . $branch;
        $shortId              = substr($projectId, 0, 8);
        $parentComposeProject = 'tw-' . $shortId . '-' . $parentSlug;
        $childComposeProject  = 'tw-' . $shortId . '-' . $branchSlug;
        $parentDc             = 'NO_COLOR=1 docker compose --project-name ' . escapeshellarg($parentComposeProject);
        $childDc              = 'NO_COLOR=1 docker compose --project-name ' . escapeshellarg($childComposeProject);

        // Probed lazily against the first destination volume and reused for the whole run,
        // since all Docker named volumes share the same underlying filesystem.
        $reflink = null;

        foreach ($config->services as $service) {
            $type = $service->type->value;

            if (
                ! str_starts_with($type, 'postgresql:')
                && ! str_starts_with($type, 'mysql:')
                && ! str_starts_with($type, 'mariadb:')
            ) {
                continue;
            }

            $serviceSlug = $this->slugify($service->name);
            $volName     = $serviceSlug . '-data';
            $src         = $parentComposeProject . '_' . $volName;
            $dst         = $childComposeProject . '_' . $volName;

            $log('[Sync] Syncing DB volume "' . $volName . '" from parent...');

            $reflink ??= $this->probeReflink($sftp, $dst);

            $this->copyVolume(
                $sftp,
                $src,
                $dst,
                $reflink,
                $this->stopper($sftp, $parentDir, $parentDc, $childDir, $childDc, [$serviceSlug], 'stop'),
                $this->stopper($sftp, $parentDir, $parentDc, $childDir, $childDc, [$serviceSlug], 'start'),
                $log,
            );

            $log('[Sync] Volume "' . $volName . '" synced.');
        }

        foreach ($config->applications as $app) {
            $appSlug = $this->slugify($app->name);

            // The app mount volume is shared by the main service and every worker/cron sidecar,
            // so all of them must be stopped for a consistent copy.
            $services = [$appSlug];

            foreach ($app->workers as $workerDef) {
                $services[] = $appSlug . '-worker-' . $this->slugify($workerDef->name);
            }

            if ($app->crons !== []) {
                $services[] = $appSlug . '-cron';
            }

            foreach ($app->mounts as $mount) {
                if ($mount->source !== MountSource::LOCAL || ! $mount->cloneFromParent) {
                    continue;
                }

                $mountSlug = $this->slugify($mount->name);
                $volName   = $appSlug . '-' . $mountSlug;
                $src       = $parentComposeProject . '_' . $volName;
                $dst       = $childComposeProject . '_' . $volName;

                $log('[Sync] Syncing app mount volume "' . $volName . '"...');

                $reflink ??= $this->probeReflink($sftp, $dst);

                $this->copyVolume(
                    $sftp,
                    $src,
                    $dst,
                    $reflink,
                    $this->stopper($sftp, $parentDir, $parentDc, $childDir, $childDc, $services, 'stop'),
                    $this->stopper($sftp, $parentDir, $parentDc, $childDir, $childDc, $services, 'start'),
                    $log,
                );

                $log('[Sync] Volume "' . $volName . '" synced.');
            }
        }
    }

    /**
     * @param Closure(): void       $stop
     * @param Closure(): void       $start
     * @param Closure(string): void $log
     */
    private function copyVolume(
        SFTP $sftp,
        string $src,
        string $dst,
        bool $reflink,
        Closure $stop,
        Closure $start,
        Closure $log,
    ): void {
        if ($reflink) {
            $log('[Sync] Using reflink fast-path for "' . $dst . '".');
            $stop();
            $sftp->exec($this->reflinkCmd($src, $dst));
            $start();

            return;
        }

        $log('[Sync] Using rsync 2-pass for "' . $dst . '".');
        // Pass 1: source still running — bulk copy, deliberately throwaway-consistent.
        $sftp->exec($this->rsyncCmd($src, $dst));
        // Pass 2: source stopped — copy only the delta for a consistent result.
        $stop();
        $sftp->exec($this->rsyncCmd($src, $dst));
        $start();
    }

    /**
     * @param list<string> $services
     *
     * @return Closure(): void
     */
    private function stopper(
        SFTP $sftp,
        string $parentDir,
        string $parentDc,
        string $childDir,
        string $childDc,
        array $services,
        string $action,
    ): Closure {
        return static function () use ($sftp, $parentDir, $parentDc, $childDir, $childDc, $services, $action): void {
            foreach ($services as $service) {
                $sftp->exec('cd ~/' . $parentDir . ' && ' . $parentDc . ' ' . $action . ' '
                    . escapeshellarg($service) . ' 2>&1');
                $sftp->exec('cd ~/' . $childDir . ' && ' . $childDc . ' ' . $action . ' '
                    . escapeshellarg($service) . ' 2>&1');
            }
        };
    }

    private function rsyncCmd(string $src, string $dst): string
    {
        return 'docker run --rm'
            . ' -v ' . escapeshellarg($src) . ':/src:ro'
            . ' -v ' . escapeshellarg($dst) . ':/dst'
            . ' ' . self::IMAGE . ' sh -c '
            . escapeshellarg('apk add --no-cache rsync >/dev/null 2>&1 && rsync -a --delete /src/ /dst/')
            . ' 2>&1';
    }

    private function reflinkCmd(string $src, string $dst): string
    {
        // Mirror the parent: clear the destination first (harmless when empty on first deploy),
        // then clone via GNU cp reflink. `;` keeps cp running even if the glob matched nothing.
        return 'docker run --rm'
            . ' -v ' . escapeshellarg($src) . ':/src:ro'
            . ' -v ' . escapeshellarg($dst) . ':/dst'
            . ' ' . self::IMAGE . ' sh -c '
            . escapeshellarg(
                'apk add --no-cache coreutils >/dev/null 2>&1;'
                . ' rm -rf /dst/..?* /dst/.[!.]* /dst/* 2>/dev/null;'
                . ' /usr/bin/cp -a --reflink=always /src/. /dst/',
            )
            . ' 2>&1';
    }

    private function probeReflink(SFTP $sftp, string $volume): bool
    {
        $sftp->exec(
            'docker run --rm -v ' . escapeshellarg($volume) . ':/d ' . self::IMAGE . ' sh -c '
            . escapeshellarg(
                'apk add --no-cache coreutils >/dev/null 2>&1;'
                . ' head -c 4096 /dev/urandom > /d/.rfprobe_a;'
                . ' /usr/bin/cp --reflink=always /d/.rfprobe_a /d/.rfprobe_b;'
                . ' rc=$?; rm -f /d/.rfprobe_a /d/.rfprobe_b; exit $rc',
            ) . ' >/dev/null 2>&1',
        );

        return $sftp->getExitStatus() === 0;
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
