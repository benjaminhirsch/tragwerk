<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Docker\DockerInstallScript;
use Tragwerk\Domain\Enum\CredentialPrivilege;

use function str_contains;

final class DockerInstallScriptTest extends TestCase
{
    private const string PACKAGES = 'docker-ce docker-ce-cli containerd.io '
        . 'docker-buildx-plugin docker-compose-plugin';

    #[Test]
    #[DataProvider('aptDistros')]
    public function aptBranchUsesTheOfficialRepository(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringContainsString('apt-get install -y ' . self::PACKAGES, $script);
        self::assertStringContainsString('https://download.docker.com/linux/' . $distro . '/gpg', $script);
        self::assertStringContainsString('/etc/apt/sources.list.d/docker.list', $script);
        self::assertStringNotContainsString('get.docker.com', $script);
    }

    #[Test]
    #[DataProvider('dnfDistros')]
    public function dnfBranchUsesTheOfficialRepository(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringContainsString('dnf install -y ' . self::PACKAGES, $script);
        self::assertStringContainsString(
            'https://download.docker.com/linux/' . $distro . '/docker-ce.repo',
            $script,
        );
        self::assertStringNotContainsString('get.docker.com', $script);
    }

    #[Test]
    #[DataProvider('rhelRebuilds')]
    public function rhelRebuildsUseTheCentosRepository(string $distro): void
    {
        // Docker publishes no rocky/almalinux repo; the RHEL rebuilds use the centos repo.
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringContainsString('https://download.docker.com/linux/centos/docker-ce.repo', $script);
        self::assertStringContainsString('dnf install -y ' . self::PACKAGES, $script);
        self::assertStringNotContainsString('linux/' . $distro, $script);
    }

    #[Test]
    public function dnfBranchDropsRepoFileAndAvoidsConfigManager(): void
    {
        // Regression: dnf5 (Fedora 41+) removed `config-manager --add-repo`, so the install must
        // drop the .repo file directly instead of relying on that plugin subcommand.
        foreach (['rhel', 'fedora', 'centos', 'rocky', 'almalinux'] as $distro) {
            $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

            self::assertStringContainsString('/etc/yum.repos.d/docker-ce.repo', $script, $distro);
            self::assertStringNotContainsString('config-manager', $script, $distro);
        }
    }

    #[Test]
    #[DataProvider('allDistros')]
    public function rootModeNeverUsesSudo(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringNotContainsString('sudo', $script);
    }

    #[Test]
    #[DataProvider('allDistros')]
    public function sudoModePrefixesEveryPrivilegedCommand(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Sudo, 'deploy');

        $isApt = str_contains($script, 'apt-get');

        if ($isApt) {
            self::assertStringContainsString('sudo -n apt-get update', $script);
            self::assertStringContainsString('sudo -n apt-get install -y', $script);
            self::assertStringContainsString('sudo -n curl -fsSL', $script);
        } else {
            self::assertStringContainsString('sudo -n dnf install -y', $script);
        }

        self::assertStringContainsString('sudo -n systemctl enable --now docker', $script);
    }

    #[Test]
    #[DataProvider('allDistros')]
    public function alwaysEnablesTheDaemon(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringContainsString('systemctl enable --now docker', $script);
    }

    #[Test]
    #[DataProvider('allDistros')]
    public function sudoModeAddsUserToDockerGroup(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Sudo, 'deploy');

        self::assertStringContainsString('usermod -aG docker deploy', $script);
    }

    #[Test]
    #[DataProvider('allDistros')]
    public function rootModeDoesNotTouchTheDockerGroup(string $distro): void
    {
        $script = (new DockerInstallScript())->build($distro, CredentialPrivilege::Root, 'deploy');

        self::assertStringNotContainsString('usermod', $script);
    }

    #[Test]
    public function scriptContainsNoSingleQuotes(): void
    {
        // The result is wrapped in `bash -c '...'` on the remote host; a single quote would break it.
        foreach (['ubuntu', 'debian', 'rhel', 'fedora', 'centos', 'rocky', 'almalinux'] as $distro) {
            foreach (CredentialPrivilege::cases() as $privilege) {
                $script = (new DockerInstallScript())->build($distro, $privilege, 'deploy');
                self::assertStringNotContainsString("'", $script, $distro . '/' . $privilege->value);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function aptDistros(): iterable
    {
        yield 'ubuntu' => ['ubuntu'];
        yield 'debian' => ['debian'];
    }

    /** @return iterable<string, array{string}> */
    public static function dnfDistros(): iterable
    {
        yield 'rhel'   => ['rhel'];
        yield 'fedora' => ['fedora'];
        yield 'centos' => ['centos'];
    }

    /** @return iterable<string, array{string}> */
    public static function rhelRebuilds(): iterable
    {
        yield 'rocky'     => ['rocky'];
        yield 'almalinux' => ['almalinux'];
    }

    /** @return iterable<string, array{string}> */
    public static function allDistros(): iterable
    {
        yield from self::aptDistros();
        yield from self::dnfDistros();
        yield from self::rhelRebuilds();
    }
}
