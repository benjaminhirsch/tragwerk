<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

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

use function strpos;

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
    public function noExtensionLayerWhenNoExtensions(): void
    {
        $output = $this->generator->generate(self::app());

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
        self::assertStringNotContainsString('apt-get', $output->dockerfileContent);
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
}
