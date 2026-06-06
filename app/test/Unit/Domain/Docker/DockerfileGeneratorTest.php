<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Docker\DockerfileGenerator;
use Tragwerk\Domain\Docker\ServiceImageResolver;
use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\HookType;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\ExtensionConfig;
use Tragwerk\Domain\Model\HookConfig;
use Tragwerk\Domain\Model\LocationConfig;
use Tragwerk\Domain\Model\WebConfig;
use Tragwerk\Domain\Model\WorkerConfig;

use function strpos;
use function substr_count;

final class DockerfileGeneratorTest extends TestCase
{
    private DockerfileGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DockerfileGenerator(new ServiceImageResolver());
    }

    /**
     * @param list<HookConfig>      $hooks
     * @param list<ExtensionConfig> $extensions
     */
    private static function app(
        string $name = 'app',
        ApplicationRuntime $type = ApplicationRuntime::PHP85,
        string $root = '/',
        array $hooks = [],
        array $extensions = [],
    ): ApplicationConfig {
        return new ApplicationConfig(
            name: $name,
            type: $type,
            root: $root,
            web: new WebConfig([new LocationConfig(path: '/', root: 'public')]),
            hooks: $hooks,
            extensions: $extensions,
        );
    }

    private static function hook(HookType $type, string $value): HookConfig
    {
        return new HookConfig(type: $type, value: $value);
    }

    #[Test]
    public function dockerfileNameContainsSlug(): void
    {
        $output = $this->generator->generate(self::app('My Test Project'));

        self::assertSame('Dockerfile.my-test-project', $output->dockerfileName);
    }

    #[Test]
    public function dockerfileStartsWithCorrectFromInstruction(): void
    {
        $output = $this->generator->generate(self::app(type: ApplicationRuntime::PHP85));

        self::assertStringContainsString('FROM dunglas/frankenphp:php8.5', $output->dockerfileContent);
    }

    #[Test]
    public function dockerfileContainsWorkdirAndCopy(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertStringContainsString('WORKDIR /app', $output->dockerfileContent);
        self::assertStringContainsString('COPY . .', $output->dockerfileContent);
    }

    #[Test]
    public function nonRootAppRootUsedInCopy(): void
    {
        $output = $this->generator->generate(self::app(root: '/backend'));

        self::assertStringContainsString('COPY backend .', $output->dockerfileContent);
        self::assertStringNotContainsString('COPY . .', $output->dockerfileContent);
    }

    #[Test]
    public function buildHookBecomesRunInstruction(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, 'composer install --no-dev'),
        ]));

        self::assertStringContainsString('RUN composer install --no-dev', $output->dockerfileContent);
    }

    #[Test]
    public function multiLineBuildHookJoinedWithAnd(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, "composer install --no-dev\nphp artisan config:cache"),
        ]));

        self::assertStringContainsString(
            'RUN composer install --no-dev' . " \\\n    && " . 'php artisan config:cache',
            $output->dockerfileContent,
        );
    }

    #[Test]
    public function buildHookWithWhitespaceOnlyLinesIgnored(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, "  \n  composer install  \n  "),
        ]));

        self::assertStringContainsString('RUN composer install', $output->dockerfileContent);
        self::assertStringNotContainsString('RUN  ', $output->dockerfileContent);
    }

    #[Test]
    public function noEntrypointWhenOnlyBuildHooks(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, 'composer install'),
        ]));

        self::assertNull($output->entrypointName);
        self::assertNull($output->entrypointContent);
        self::assertStringNotContainsString('ENTRYPOINT', $output->dockerfileContent);
    }

    #[Test]
    public function noEntrypointWhenNoHooks(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNull($output->entrypointName);
        self::assertNull($output->entrypointContent);
    }

    #[Test]
    public function entrypointNameContainsSlug(): void
    {
        $output = $this->generator->generate(self::app('My App', hooks: [
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
        ]));

        self::assertSame('docker-entrypoint.my-app.sh', $output->entrypointName);
    }

    #[Test]
    public function deployHookInEntrypointBeforeExec(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
        ]));

        self::assertNotNull($output->entrypointContent);
        self::assertStringContainsString('php artisan migrate', $output->entrypointContent);
        self::assertStringContainsString('exec "$@"', $output->entrypointContent);

        $migratePos = strpos($output->entrypointContent, 'php artisan migrate');
        $execPos    = strpos($output->entrypointContent, 'exec "$@"');
        self::assertNotFalse($migratePos);
        self::assertNotFalse($execPos);
        self::assertLessThan($execPos, $migratePos);
    }

    #[Test]
    public function postDeployHookRunsInBackground(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::POST_DEPLOY, 'php artisan cache:warm'),
        ]));

        self::assertNotNull($output->entrypointContent);
        self::assertStringContainsString('php artisan cache:warm', $output->entrypointContent);
        self::assertStringContainsString(') &', $output->entrypointContent);

        $warmPos       = strpos($output->entrypointContent, 'php artisan cache:warm');
        $backgroundPos = strpos($output->entrypointContent, ') &');
        $execPos       = strpos($output->entrypointContent, 'exec "$@"');
        self::assertNotFalse($warmPos);
        self::assertNotFalse($backgroundPos);
        self::assertNotFalse($execPos);
        self::assertLessThan($backgroundPos, $warmPos);
        self::assertLessThan($execPos, $backgroundPos);
    }

    #[Test]
    public function deployHookRunsBeforePostDeployInBackground(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
            self::hook(HookType::POST_DEPLOY, 'php artisan cache:warm'),
        ]));

        self::assertNotNull($output->entrypointContent);
        $migratePos = strpos($output->entrypointContent, 'php artisan migrate');
        $warmPos    = strpos($output->entrypointContent, 'php artisan cache:warm');
        self::assertNotFalse($migratePos);
        self::assertNotFalse($warmPos);
        self::assertLessThan($warmPos, $migratePos);
    }

    #[Test]
    public function entrypointStartsWithShebangAndSetE(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
        ]));

        self::assertNotNull($output->entrypointContent);
        self::assertStringStartsWith("#!/bin/sh\nset -e\n", $output->entrypointContent);
    }

    #[Test]
    public function dockerfileReferencesEntrypointWhenDeployHookPresent(): void
    {
        $output = $this->generator->generate(self::app('app', hooks: [
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
        ]));

        self::assertStringContainsString('COPY docker-entrypoint.app.sh', $output->dockerfileContent);
        self::assertStringContainsString('ENTRYPOINT ["docker-entrypoint.sh"]', $output->dockerfileContent);
        self::assertStringContainsString(
            'CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]',
            $output->dockerfileContent,
        );
    }

    #[Test]
    public function buildAndDeployHooksCombined(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, 'composer install --no-dev'),
            self::hook(HookType::DEPLOY, 'php artisan migrate'),
        ]));

        self::assertStringContainsString('RUN composer install --no-dev', $output->dockerfileContent);
        self::assertStringContainsString('ENTRYPOINT', $output->dockerfileContent);
        self::assertNotNull($output->entrypointContent);
        self::assertStringContainsString('php artisan migrate', $output->entrypointContent);
    }

    #[Test]
    public function phpAppAlwaysInstallsUnzip(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertStringContainsString('unzip', $output->dockerfileContent);
        self::assertStringContainsString('apt-get', $output->dockerfileContent);
        self::assertStringNotContainsString('docker-php-ext-install', $output->dockerfileContent);
    }

    #[Test]
    public function phpExtensionsWithNoAptDepsEmitDockerPhpExtInstall(): void
    {
        $output = $this->generator->generate(self::app(extensions: [
            new ExtensionConfig('gettext'),
            new ExtensionConfig('sockets'),
        ]));

        self::assertStringContainsString('docker-php-ext-install gettext sockets', $output->dockerfileContent);
        self::assertStringContainsString('unzip', $output->dockerfileContent);
    }

    #[Test]
    public function phpExtensionsWithAptDepsEmitAptInstallAndDockerPhpExtInstall(): void
    {
        $output = $this->generator->generate(self::app(extensions: [
            new ExtensionConfig('intl'),
        ]));

        self::assertStringContainsString('libicu-dev', $output->dockerfileContent);
        self::assertStringContainsString('docker-php-ext-install intl', $output->dockerfileContent);
    }

    #[Test]
    public function extensionLayerAppearsBeforeCopyStep(): void
    {
        $output = $this->generator->generate(self::app(extensions: [
            new ExtensionConfig('intl'),
        ]));

        $extPos  = strpos($output->dockerfileContent, 'docker-php-ext-install');
        $copyPos = strpos($output->dockerfileContent, 'COPY ');

        self::assertNotFalse($extPos);
        self::assertNotFalse($copyPos);
        self::assertLessThan($copyPos, $extPos);
    }

    #[Test]
    public function mixedExtensionsDedupAptPackages(): void
    {
        $output = $this->generator->generate(self::app(extensions: [
            new ExtensionConfig('gettext'),
            new ExtensionConfig('intl'),
            new ExtensionConfig('sockets'),
        ]));

        self::assertStringContainsString('docker-php-ext-install gettext intl sockets', $output->dockerfileContent);
        self::assertStringContainsString('libicu-dev', $output->dockerfileContent);
    }

    #[Test]
    public function caddyfileGeneratedForPhpApp(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNotNull($output->caddyfileName);
        self::assertNotNull($output->caddyfileContent);
        self::assertSame('Caddyfile.app', $output->caddyfileName);
    }

    #[Test]
    public function caddyfileContainsFrankenPhpAndDocumentRoot(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString('frankenphp', $output->caddyfileContent);
        self::assertStringContainsString('root * /app/public', $output->caddyfileContent);
    }

    #[Test]
    public function caddyfileUsesPhpServerWhenPassthruSet(): void
    {
        $app    = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            hooks: [],
            extensions: [],
        );
        $output = $this->generator->generate($app);

        self::assertNotNull($output->caddyfileContent);
        // global "order" line + server-block directive = 2 occurrences of php_server
        // global "order" line = 1 occurrence of file_server (no server-block file_server)
        self::assertSame(2, substr_count($output->caddyfileContent, 'php_server'));
        self::assertSame(1, substr_count($output->caddyfileContent, 'file_server'));
    }

    #[Test]
    public function caddyfileUsesFileServerWhenNoPassthru(): void
    {
        $app    = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public')]),
            hooks: [],
            extensions: [],
        );
        $output = $this->generator->generate($app);

        self::assertNotNull($output->caddyfileContent);
        // global "order" line = 1 occurrence of php_server (no server-block php_server)
        // global "order" line + server-block directive = 2 occurrences of file_server
        self::assertSame(1, substr_count($output->caddyfileContent, 'php_server'));
        self::assertSame(2, substr_count($output->caddyfileContent, 'file_server'));
    }

    #[Test]
    public function caddyfileMultipleLocationsGetPrefixedPaths(): void
    {
        $app    = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([
                new LocationConfig(path: '/', root: 'public', passthru: '/index.php'),
                new LocationConfig(path: '/static', root: 'static'),
            ]),
            hooks: [],
            extensions: [],
        );
        $output = $this->generator->generate($app);

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString(':80/', $output->caddyfileContent);
        self::assertStringContainsString(':80/static', $output->caddyfileContent);
    }

    #[Test]
    public function dockerfileCopiesCaddyfile(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertStringContainsString('COPY Caddyfile.app /etc/caddy/Caddyfile', $output->dockerfileContent);
    }

    #[Test]
    public function caddyfileCopyAppearsAfterBuildSteps(): void
    {
        $output = $this->generator->generate(self::app(hooks: [
            self::hook(HookType::BUILD, 'composer install'),
        ]));

        $runPos   = strpos($output->dockerfileContent, 'RUN composer install');
        $caddyPos = strpos($output->dockerfileContent, 'COPY Caddyfile');

        self::assertNotFalse($runPos);
        self::assertNotFalse($caddyPos);
        self::assertLessThan($caddyPos, $runPos);
    }

    #[Test]
    public function caddyfileEncodesCompression(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString('encode zstd br gzip', $output->caddyfileContent);
    }

    #[Test]
    public function caddyfileEnablesPrometheusMetrics(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString('servers {', $output->caddyfileContent);
        self::assertStringContainsString('metrics', $output->caddyfileContent);
    }

    #[Test]
    public function caddyfileHasNoWorkerBlockByDefault(): void
    {
        $output = $this->generator->generate(self::app());

        self::assertNotNull($output->caddyfileContent);
        self::assertStringNotContainsString('worker {', $output->caddyfileContent);
    }

    #[Test]
    public function caddyfileWorkerBlockDerivesFileFromPassthruLocation(): void
    {
        $output = $this->generator->generate(self::workerApp(new WorkerConfig(count: 4)));

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString('worker {', $output->caddyfileContent);
        self::assertStringContainsString('file /app/public/index.php', $output->caddyfileContent);
        self::assertStringContainsString('num 4', $output->caddyfileContent);
    }

    #[Test]
    public function caddyfileWorkerBlockOmitsNumWhenCountAbsent(): void
    {
        $output = $this->generator->generate(self::workerApp(new WorkerConfig()));

        self::assertNotNull($output->caddyfileContent);
        self::assertStringContainsString('worker {', $output->caddyfileContent);
        self::assertStringNotContainsString('num ', $output->caddyfileContent);
    }

    #[Test]
    public function workerModeWithoutPassthruLocationThrows(): void
    {
        $app = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public')]),
            workerMode: new WorkerConfig(count: 2),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one');

        $this->generator->generate($app);
    }

    #[Test]
    public function workerModeWithMultiplePassthruLocationsThrows(): void
    {
        $app = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([
                new LocationConfig(path: '/', root: 'public', passthru: '/index.php'),
                new LocationConfig(path: '/api', root: 'api', passthru: '/api.php'),
            ]),
            workerMode: new WorkerConfig(count: 2),
        );

        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate($app);
    }

    private static function workerApp(WorkerConfig $worker): ApplicationConfig
    {
        return new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workerMode: $worker,
        );
    }
}
