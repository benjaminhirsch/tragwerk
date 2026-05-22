<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;

use function assert;
use function in_array;
use function is_string;
use function sprintf;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;

#[AsCommand(name: 'server:setup', description: 'Run server setup for a given setup job')]
final class SetupServerCommand extends Command
{
    public function __construct(
        private readonly SetupJobRepository $setupJobRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CredentialRepository $credentialRepository,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('job-id', InputArgument::REQUIRED, 'SetupJob UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobId = $input->getArgument('job-id');
        assert(is_string($jobId));

        if (! SetupJobIdentifier::isValid($jobId)) {
            $output->writeln('<error>Invalid job ID</error>');

            return Command::FAILURE;
        }

        try {
            $job = $this->setupJobRepository->getById(SetupJobIdentifier::fromString($jobId));
            assert($job instanceof SetupJob);
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Job not found: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $lock = $this->lockFactory->createLock('server-setup:' . $job->serverId->toString(), ttl: 600.0);
        if (! $lock->acquire()) {
            $this->fail($job, "Another setup job is already running for this server.\n");

            return Command::FAILURE;
        }

        $this->setupJobRepository->updateStatus($job->id, SetupJobStatus::Running);

        try {
            try {
                $server = $this->serverRepository->getById($job->serverId);
                assert($server instanceof Server);
            } catch (Throwable $e) {
                $this->fail($job, sprintf("Server not found: %s\n", $e->getMessage()));

                return Command::FAILURE;
            }

            if ($server->credentialId === null) {
                $this->fail($job, "No credential assigned to this server.\n");

                return Command::FAILURE;
            }

            try {
                $credential = $this->credentialRepository->getById($server->credentialId);
                assert($credential instanceof Credential);
            } catch (Throwable $e) {
                $this->fail($job, sprintf("Credential not found: %s\n", $e->getMessage()));

                return Command::FAILURE;
            }

            if ($credential->privateKey === null) {
                $this->fail($job, "Credential has no SSH key.\n");

                return Command::FAILURE;
            }

            $ssh = new SSH2($server->host, 22, 30);

            try {
                $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
            } catch (NoKeyLoadedException $e) {
                $this->fail($job, sprintf("Failed to load SSH key: %s\n", $e->getMessage()));

                return Command::FAILURE;
            }

            $this->append($job, sprintf("Connecting to %s as %s...\n", $server->host, $credential->username));

            if (! $ssh->login($credential->username, $key)) {
                $this->fail($job, sprintf("SSH login failed for user '%s'.\n", $credential->username));

                return Command::FAILURE;
            }

            $this->append($job, "Connected.\n\n");

            if (! $this->checkSupportedDistro($ssh, $job)) {
                return Command::FAILURE;
            }

            $ssh = $this->ensureCurl($ssh, $server->host, $credential->username, $key, $job);
            if ($ssh === null) {
                return Command::FAILURE;
            }

            $dockerVersion = $this->checkDocker($ssh, $job);

            if ($dockerVersion === null) {
                $this->append($job, "\nInstalling Docker...\n");
                $this->append($job, "Running: curl -fsSL https://get.docker.com | sh\n\n");

                $ssh->exec(
                    'nohup bash -c \''
                    . 'curl -fsSL https://get.docker.com | sh > /tmp/docker-install.log 2>&1;'
                    . ' echo $? > /tmp/docker-install.exit'
                    . '\' > /dev/null 2>&1 &',
                );

                $ssh = $this->reconnect($server->host, $credential->username, $key, $job);
                if ($ssh === null) {
                    return Command::FAILURE;
                }

                $this->append($job, "Waiting for Docker installation to complete...\n");

                $lastLogSize = 0;

                for ($attempt = 0; $attempt < 60; $attempt++) {
                    $ssh->exec('sleep 3');

                    $ssh = $this->reconnect($server->host, $credential->username, $key, $job);
                    if ($ssh === null) {
                        return Command::FAILURE;
                    }

                    $log = $ssh->exec('tail -c +' . ($lastLogSize + 1) . ' /tmp/docker-install.log 2>/dev/null');
                    if (is_string($log) && $log !== '') {
                        $this->append($job, $log);
                        $lastLogSize += strlen($log);
                    }

                    $done = $ssh->exec('test -f /tmp/docker-install.exit && echo done || echo running');
                    if (is_string($done) && trim($done) === 'done') {
                        break;
                    }
                }

                $ssh = $this->reconnect($server->host, $credential->username, $key, $job);
                if ($ssh === null) {
                    return Command::FAILURE;
                }

                $dockerVersion = $this->checkDocker($ssh, $job);
                if ($dockerVersion === null) {
                    $this->fail(
                        $job,
                        "\nDocker binary not found after installation."
                        . " Check the install output above for errors.\n",
                    );

                    return Command::FAILURE;
                }

                $this->append($job, "\nDocker installation complete.\n");
            }

            $ssh = $this->reconnect($server->host, $credential->username, $key, $job);
            if ($ssh === null) {
                return Command::FAILURE;
            }

            $dockerComposeVersion = $this->checkDockerCompose($ssh, $server->host, $credential->username, $key, $job);

            $this->serverRepository->updateVersions($server->id, $dockerVersion, $dockerComposeVersion);

            $this->setupJobRepository->updateStatus($job->id, SetupJobStatus::Completed);

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function checkSupportedDistro(SSH2 $ssh, SetupJob $job): bool
    {
        $result   = $ssh->exec('. /etc/os-release 2>/dev/null && echo "$ID"');
        $distroId = is_string($result) ? strtolower(trim($result)) : '';

        $supported = ['ubuntu', 'debian', 'rhel', 'fedora', 'centos'];

        if (! in_array($distroId, $supported, true)) {
            $this->fail(
                $job,
                sprintf(
                    "Unsupported distribution: %s.\nSupported: Ubuntu, Debian, RHEL, Fedora, CentOS.\n",
                    $distroId !== '' ? $distroId : 'unknown',
                ),
            );

            return false;
        }

        $this->append($job, sprintf("Distribution: %s\n", $distroId));

        return true;
    }

    private function ensureCurl(SSH2 $ssh, string $host, string $username, PrivateKey $key, SetupJob $job): SSH2|null
    {
        $this->append($job, "Checking if curl is installed...\n");
        $result = $ssh->exec('command -v curl 2>/dev/null');
        if (is_string($result) && trim($result) !== '') {
            return $ssh;
        }

        $this->append($job, "curl not found, installing...\n");

        $install = 'if command -v apt-get >/dev/null 2>&1; then apt-get install -y curl'
            . '; elif command -v dnf >/dev/null 2>&1; then dnf install -y curl'
            . '; elif command -v yum >/dev/null 2>&1; then yum install -y curl'
            . '; elif command -v zypper >/dev/null 2>&1; then zypper install -y curl'
            . '; elif command -v apk >/dev/null 2>&1; then apk add curl'
            . '; else echo "No supported package manager found"; exit 1; fi';

        $ssh->exec($install . ' 2>&1', function (string $chunk) use ($job): void {
            $this->append($job, $chunk);
        });

        $ssh = $this->reconnect($host, $username, $key, $job);
        if ($ssh === null) {
            return null;
        }

        $result = $ssh->exec('command -v curl 2>/dev/null');
        if (! is_string($result) || trim($result) === '') {
            $this->fail($job, "Could not install curl. Aborting.\n");

            return null;
        }

        return $ssh;
    }

    private function checkDocker(SSH2 $ssh, SetupJob $job): string|null
    {
        $this->append($job, "Checking Docker...\n");
        $result = $ssh->exec('docker --version 2>&1');

        if (is_string($result) && str_contains($result, 'Docker version')) {
            $version = trim($result);
            $this->append($job, sprintf("  %s\n", $version));

            return $version;
        }

        $this->append($job, "  Docker not installed.\n");

        return null;
    }

    private function checkDockerCompose(
        SSH2 $ssh,
        string $host,
        string $username,
        PrivateKey $key,
        SetupJob $job,
    ): string|null {
        $this->append($job, "\nChecking Docker Compose...\n");
        $result = $ssh->exec('docker compose version 2>&1');

        if (is_string($result) && str_contains($result, 'Docker Compose')) {
            $version = trim($result);
            $this->append($job, sprintf("  %s\n", $version));

            return $version;
        }

        $this->append($job, "  Docker Compose plugin not found, installing...\n");

        $install = 'if command -v apt-get >/dev/null 2>&1; then'
            . ' while pgrep -x apt-get > /dev/null 2>&1; do sleep 2; done'
            . ' && apt-get install -y docker-compose-plugin'
            . '; elif command -v dnf >/dev/null 2>&1; then'
            . ' while pgrep -x dnf > /dev/null 2>&1; do sleep 2; done'
            . ' && dnf install -y docker-compose-plugin'
            . '; elif command -v yum >/dev/null 2>&1; then'
            . ' while pgrep -x yum > /dev/null 2>&1; do sleep 2; done'
            . ' && yum install -y docker-compose-plugin'
            . '; elif command -v zypper >/dev/null 2>&1; then'
            . ' while pgrep -x zypper > /dev/null 2>&1; do sleep 2; done'
            . ' && zypper install -y docker-compose-plugin'
            . '; elif command -v apk >/dev/null 2>&1; then'
            . ' while pgrep -x apk > /dev/null 2>&1; do sleep 2; done'
            . ' && apk add docker-cli-compose'
            . '; else echo "No supported package manager found"; exit 1; fi';

        $ssh->exec($install . ' 2>&1', function (string $chunk) use ($job): void {
            $this->append($job, $chunk);
        });

        $ssh = $this->reconnect($host, $username, $key, $job);
        if ($ssh === null) {
            return null;
        }

        $result = $ssh->exec('docker compose version 2>&1');
        if (is_string($result) && str_contains($result, 'Docker Compose')) {
            $version = trim($result);
            $this->append($job, sprintf("  %s\n", $version));

            return $version;
        }

        $this->append($job, "  Docker Compose plugin could not be installed.\n");

        return null;
    }

    private function reconnect(string $host, string $username, PrivateKey $key, SetupJob $job): SSH2|null
    {
        $ssh = new SSH2($host, 22, 30);
        if (! $ssh->login($username, $key)) {
            $this->fail($job, "Reconnect failed after install.\n");

            return null;
        }

        return $ssh;
    }

    private function append(SetupJob $job, string $text): void
    {
        $this->setupJobRepository->appendOutput($job->id, $text);
    }

    private function fail(SetupJob $job, string $message): void
    {
        $this->setupJobRepository->appendOutput($job->id, $message);
        $this->setupJobRepository->updateStatus($job->id, SetupJobStatus::Failed);
    }
}
