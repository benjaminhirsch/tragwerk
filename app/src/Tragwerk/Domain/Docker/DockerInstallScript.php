<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\CredentialPrivilege;

use function implode;
use function in_array;
use function sprintf;

/**
 * Builds the shell script that installs Docker Engine + Compose on a target host using the
 * official Docker package repository (https://docs.docker.com/engine/install/).
 *
 * This replaces the `curl -fsSL https://get.docker.com | sh` convenience script, which Docker
 * documents as testing/dev only: the repo method uses GPG-verified, pinnable, signed packages.
 *
 * Kept as a pure builder (no SSH, no I/O) so the distro branching and sudo prefixing are
 * unit-testable. {@see \Tragwerk\Application\Cli\Command\SetupServerCommand} base64-encodes the
 * result and runs it detached on the remote host.
 */
final readonly class DockerInstallScript
{
    private const array DNF_DISTROS = ['rhel', 'fedora', 'centos', 'rocky', 'almalinux'];

    /**
     * Docker publishes no dedicated repo for the RHEL rebuilds (Rocky/Alma are 100% binary
     * compatible with RHEL): they use the CentOS repo.
     */
    private const array REPO_DISTRO = ['rocky' => 'centos', 'almalinux' => 'centos'];

    private const string PACKAGES = 'docker-ce docker-ce-cli containerd.io '
        . 'docker-buildx-plugin docker-compose-plugin';

    /**
     * @param string $distroId the `$ID` from /etc/os-release (ubuntu|debian|rhel|fedora|centos|
     *                         rocky|almalinux); anything outside the dnf family takes the apt branch
     * @param string $username login user, added to the docker group in sudo mode so later
     *                         non-sudo `docker` calls work
     */
    public function build(string $distroId, CredentialPrivilege $privilege, string $username): string
    {
        $sudo = $privilege->sudoPrefix();

        $body = in_array($distroId, self::DNF_DISTROS, true)
            ? $this->dnfBranch($distroId, $sudo)
            : $this->aptBranch($distroId, $sudo);

        $lines = ['set -e', $body, $sudo . 'systemctl enable --now docker'];

        // A non-root sudoer needs docker-group membership so deploy/metrics/cron can run
        // `docker compose ...` without sudo. Root already has socket access.
        if ($privilege === CredentialPrivilege::Sudo) {
            $lines[] = sprintf('%susermod -aG docker %s', $sudo, $username);
        }

        return implode("\n", $lines) . "\n";
    }

    private function aptBranch(string $distroId, string $sudo): string
    {
        $repo = 'https://download.docker.com/linux/' . $distroId;

        return implode("\n", [
            'export DEBIAN_FRONTEND=noninteractive',
            $sudo . 'install -m 0755 -d /etc/apt/keyrings',
            $sudo . 'curl -fsSL ' . $repo . '/gpg -o /etc/apt/keyrings/docker.asc',
            $sudo . 'chmod a+r /etc/apt/keyrings/docker.asc',
            'echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] '
                . $repo . ' $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | '
                . $sudo . 'tee /etc/apt/sources.list.d/docker.list > /dev/null',
            'while pgrep -x apt-get > /dev/null 2>&1; do sleep 2; done',
            $sudo . 'apt-get update',
            $sudo . 'apt-get install -y ' . self::PACKAGES,
        ]);
    }

    private function dnfBranch(string $distroId, string $sudo): string
    {
        $repoDistro = self::REPO_DISTRO[$distroId] ?? $distroId;
        $repoUrl    = 'https://download.docker.com/linux/' . $repoDistro . '/docker-ce.repo';

        // Write the repo file directly instead of `dnf config-manager --add-repo`: dnf5 (Fedora 41+)
        // removed that flag. A plain file drop into /etc/yum.repos.d works on dnf4, dnf5 and yum
        // alike; `install -y` auto-imports the repo's GPG key (gpgcheck stays on). curl is already
        // ensured before this runs.
        return implode("\n", [
            $sudo . 'curl -fsSL ' . $repoUrl . ' -o /etc/yum.repos.d/docker-ce.repo',
            'if command -v dnf > /dev/null 2>&1; then',
            '  ' . $sudo . 'dnf install -y ' . self::PACKAGES,
            'else',
            '  ' . $sudo . 'yum install -y ' . self::PACKAGES,
            'fi',
        ]);
    }
}
