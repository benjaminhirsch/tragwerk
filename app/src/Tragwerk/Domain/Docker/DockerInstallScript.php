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

    /** Netfilter modules dockerd needs before it can install its bridge/NAT rules. */
    private const string NETFILTER_MODULES = 'nft_compat xt_addrtype xt_conntrack br_netfilter overlay';

    /**
     * @param string $distroId the `$ID` from /etc/os-release (ubuntu|debian|rhel|fedora|centos|
     *                         rocky|almalinux); anything outside the dnf family takes the apt branch
     * @param string $username login user, added to the docker group in sudo mode so later
     *                         non-sudo `docker` calls work
     */
    public function build(string $distroId, CredentialPrivilege $privilege, string $username): string
    {
        $sudo  = $privilege->sudoPrefix();
        $isDnf = in_array($distroId, self::DNF_DISTROS, true);

        $body = $isDnf
            ? $this->dnfBranch($distroId, $sudo)
            : $this->aptBranch($distroId, $sudo);

        $lines = [
            'set -e',
            $body,
            $this->netfilterModules($sudo, $isDnf),
            $this->ipForwarding($sudo),
            $this->startDaemon($sudo),
        ];

        // A non-root sudoer needs docker-group membership so deploy/metrics/cron can run
        // `docker compose ...` without sudo. Root already has socket access.
        if ($privilege === CredentialPrivilege::Sudo) {
            $lines[] = sprintf('%susermod -aG docker %s', $sudo, $username);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * dockerd installs its bridge and NAT rules through iptables/nftables and needs the matching
     * netfilter modules. Minimal RHEL-family images (Hetzner's CentOS Stream 10, for one) run a
     * kernel whose xt_* modules live in a separate kernel-modules package that is not installed,
     * and the daemon then refuses to start with
     *   Extension addrtype revision 0 not supported, missing kernel module?
     * — an error whose cause is invisible from the install output. Loading them here, and pulling
     * the package on the dnf side, turns a failed setup into a working one.
     *
     * Nothing here is fatal: a kernel without these modules is the operator's problem, but the
     * Docker installation itself has still succeeded and should not be rolled back over it.
     */
    private function netfilterModules(string $sudo, bool $isDnf): string
    {
        $lines = [
            'MISSING=',
            'for m in ' . self::NETFILTER_MODULES . '; do',
            '  ' . $sudo . 'modprobe $m > /dev/null 2>&1 || MISSING="$MISSING $m"',
            'done',
        ];

        // Debian and Ubuntu ship these modules inside linux-image-*, so there is no separate
        // package to pull; a miss there means a custom kernel, which no install script can fix.
        if (! $isDnf) {
            return implode("\n", $lines);
        }

        // Same dnf-then-yum fallback as the package install above.
        $install = static fn (string $manager): string => sprintf(
            '    %1$s%2$s install -y kernel-modules-$(uname -r) || %1$s%2$s install -y kernel-modules || true',
            $sudo,
            $manager,
        );

        return implode("\n", [
            ...$lines,
            'if [ -n "$MISSING" ]; then',
            '  if command -v dnf > /dev/null 2>&1; then',
            $install('dnf'),
            '  else',
            $install('yum'),
            '  fi',
            '  for m in $MISSING; do ' . $sudo . 'modprobe $m > /dev/null 2>&1 || true; done',
            'fi',
        ]);
    }

    /**
     * Docker's bridge network needs IPv4 forwarding and, since Docker 28, the daemon refuses to
     * start instead of enabling it itself. Minimal cloud images ship it disabled.
     *
     * IPv6 forwarding is deliberately left alone: turning it on makes the kernel stop accepting
     * router advertisements, which breaks IPv6 on providers that hand out addresses that way.
     */
    private function ipForwarding(string $sudo): string
    {
        return implode("\n", [
            'if [ "$(cat /proc/sys/net/ipv4/ip_forward 2> /dev/null)" != "1" ]; then',
            '  echo "net.ipv4.ip_forward = 1" | ' . $sudo . 'tee /etc/sysctl.d/99-tragwerk-docker.conf > /dev/null',
            '  ' . $sudo . 'sysctl -w net.ipv4.ip_forward=1 > /dev/null 2>&1 || true',
            'fi',
        ]);
    }

    /**
     * Starts the daemon, with one fallback.
     *
     * RHEL 10 and CentOS Stream 10 dropped the working iptables-nft compatibility layer:
     * /usr/sbin/iptables is there, but the kernel cannot back it, so dockerd fails with
     *   Extension addrtype revision 0 not supported, missing kernel module?
     * Docker 28+ speaks nftables natively and skips iptables entirely, which is the documented
     * way out. An existing daemon.json is never overwritten — that is the operator's file.
     *
     * The retry is not guarded: if the daemon still refuses to start, `set -e` aborts the script
     * and SetupServerCommand reports a failed setup, which is the honest outcome.
     */
    private function startDaemon(string $sudo): string
    {
        return implode("\n", [
            'DOCKER_START_FAILED=',
            $sudo . 'systemctl enable --now docker || DOCKER_START_FAILED=1',
            'if [ -n "$DOCKER_START_FAILED" ] && [ ! -f /etc/docker/daemon.json ]; then',
            '  ' . $sudo . 'mkdir -p /etc/docker',
            '  echo "{\"firewall-backend\": \"nftables\"}" | ' . $sudo . 'tee /etc/docker/daemon.json > /dev/null',
            '  ' . $sudo . 'systemctl reset-failed docker.service > /dev/null 2>&1 || true',
            '  ' . $sudo . 'systemctl start docker',
            'fi',
        ]);
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
