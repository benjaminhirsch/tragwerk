<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Deploy;

use phpseclib3\Net\SFTP;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\CronDefinitionConfig;
use Tragwerk\Domain\Model\LocationConfig;
use Tragwerk\Domain\Model\MountConfig;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Model\ServiceConfig;
use Tragwerk\Domain\Model\WebConfig;
use Tragwerk\Domain\Model\WorkerDefinitionConfig;
use Tragwerk\Infrastructure\Deploy\VolumeSyncService;

use function array_filter;
use function array_values;
use function str_contains;

final class VolumeSyncServiceTest extends TestCase
{
    private const string PROJECT_ID = '0123456789abcdef';
    private const string BRANCH     = 'feature';
    private const string PARENT     = 'main';

    /**
     * @param list<string> $commands
     *
     * @return SFTP&Stub
     */
    private function sftpRecording(array &$commands, int $exitStatus): SFTP
    {
        $sftp = self::createStub(SFTP::class);
        $sftp->method('exec')->willReturnCallback(static function (string $command) use (&$commands): string {
            $commands[] = $command;

            return '';
        });
        $sftp->method('getExitStatus')->willReturn($exitStatus);

        return $sftp;
    }

    private function config(): ProjectConfig
    {
        $app = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public')]),
            mounts: [
                new MountConfig(name: 'storage', source: MountSource::LOCAL, path: 'storage', cloneFromParent: true),
            ],
            workers: [new WorkerDefinitionConfig(name: 'mailer', command: 'run')],
            crons: [new CronDefinitionConfig(name: 'cleanup', command: 'run', schedule: '* * * * *')],
        );

        return new ProjectConfig(
            applications: [$app],
            routes: [],
            services: [new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES16)],
        );
    }

    /** @param list<string> $commands */
    private function firstIndex(array $commands, string $needle): int
    {
        foreach ($commands as $i => $command) {
            if (str_contains($command, $needle)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @param list<string> $commands
     *
     * @return list<string>
     */
    private function copyCommands(array $commands): array
    {
        // Actual volume copies mount both /src and /dst; the reflink probe only mounts /d.
        return array_values(array_filter($commands, static fn (string $c): bool => str_contains($c, '/src:ro')));
    }

    /** @param list<string> $commands */
    private function firstCopyIndex(array $commands): int
    {
        foreach ($commands as $i => $command) {
            if (str_contains($command, '/src:ro')) {
                return $i;
            }
        }

        return -1;
    }

    #[Test]
    public function reflinkSupportedUsesFastPathAndStopsSourceBeforeCopy(): void
    {
        $commands = [];
        $sftp     = $this->sftpRecording($commands, exitStatus: 0);

        (new VolumeSyncService())->syncVolumes(
            $sftp,
            self::PROJECT_ID,
            self::BRANCH,
            self::PARENT,
            $this->config(),
            static fn (string $m) => null,
        );

        $copies = $this->copyCommands($commands);

        // Two volumes (db-data + app-storage), each copied once via reflink, none via rsync.
        self::assertCount(2, $copies);
        foreach ($copies as $copy) {
            self::assertStringContainsString('--reflink=always', $copy);
            self::assertStringNotContainsString('rsync', $copy);
        }

        // A reflink snapshot is only consistent if the source is stopped first. Compare against the
        // first real copy (mounts /src) — the capability probe also uses --reflink but runs earlier.
        self::assertLessThan(
            $this->firstCopyIndex($commands),
            $this->firstIndex($commands, ' stop '),
            'source must be stopped before the reflink copy',
        );

        // DB volume path.
        self::assertStringContainsString('tw-01234567-main_db-data', $copies[0]);
        self::assertStringContainsString('tw-01234567-feature_db-data', $copies[0]);
    }

    #[Test]
    public function reflinkUnsupportedUsesRsyncTwoPassHotThenStopped(): void
    {
        $commands = [];
        $sftp     = $this->sftpRecording($commands, exitStatus: 1);

        (new VolumeSyncService())->syncVolumes(
            $sftp,
            self::PROJECT_ID,
            self::BRANCH,
            self::PARENT,
            $this->config(),
            static fn (string $m) => null,
        );

        $copies = $this->copyCommands($commands);

        // Two volumes, two passes each.
        self::assertCount(4, $copies);
        foreach ($copies as $copy) {
            self::assertStringContainsString('rsync -a --delete /src/ /dst/', $copy);
            self::assertStringNotContainsString('--reflink=always', $copy);
        }

        // Pass 1 runs hot: the first copy happens before the first service stop.
        self::assertLessThan(
            $this->firstIndex($commands, ' stop '),
            $this->firstIndex($commands, 'rsync -a --delete'),
            'the hot pass must run before any service is stopped',
        );
    }

    #[Test]
    public function appMountStopsMainWorkerAndCronServices(): void
    {
        $commands = [];
        $sftp     = $this->sftpRecording($commands, exitStatus: 0);

        (new VolumeSyncService())->syncVolumes(
            $sftp,
            self::PROJECT_ID,
            self::BRANCH,
            self::PARENT,
            $this->config(),
            static fn (string $m) => null,
        );

        // Each of app, app-worker-mailer and app-cron is stopped in both parent and child project.
        foreach (["'app'", "'app-worker-mailer'", "'app-cron'"] as $service) {
            $stops = array_filter($commands, static fn (string $c): bool => str_contains($c, ' stop ' . $service));
            self::assertCount(2, $stops, $service . ' must be stopped in parent and child');
        }
    }

    #[Test]
    public function reflinkIsProbedOnlyOnceAndReused(): void
    {
        $commands = [];
        $sftp     = $this->sftpRecording($commands, exitStatus: 0);

        (new VolumeSyncService())->syncVolumes(
            $sftp,
            self::PROJECT_ID,
            self::BRANCH,
            self::PARENT,
            $this->config(),
            static fn (string $m) => null,
        );

        // Both volumes share the same filesystem, so the reflink capability is probed once.
        $probes = array_filter($commands, static fn (string $c): bool => str_contains($c, 'rfprobe'));
        self::assertCount(1, $probes);
    }
}
