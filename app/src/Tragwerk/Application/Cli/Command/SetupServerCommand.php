<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
use function is_string;
use function sprintf;
use function str_contains;
use function trim;

#[AsCommand(name: 'server:setup', description: 'Run server setup for a given setup job')]
final class SetupServerCommand extends Command
{
    public function __construct(
        private readonly SetupJobRepository $setupJobRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CredentialRepository $credentialRepository,
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

        $job->status = SetupJobStatus::Running;
        $job->output = '';
        $this->setupJobRepository->update($job);

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

        $ssh = new SSH2($server->host);

        try {
            $key = PublicKeyLoader::load($credential->privateKey);
            assert($key instanceof PrivateKey);
        } catch (Throwable $e) {
            $this->fail($job, sprintf("Failed to load SSH key: %s\n", $e->getMessage()));

            return Command::FAILURE;
        }

        $this->append($job, sprintf("Connecting to %s as %s...\n", $server->host, $credential->username));

        if (! $ssh->login($credential->username, $key)) {
            $this->fail($job, sprintf("SSH login failed for user '%s'.\n", $credential->username));

            return Command::FAILURE;
        }

        $this->append($job, "Connected.\n\n");

        if (! $this->ensureCurl($ssh, $job)) {
            $this->fail($job, "Could not install curl. Aborting.\n");

            return Command::FAILURE;
        }

        $dockerInstalled = $this->checkDocker($ssh, $job);

        if (! $dockerInstalled) {
            $this->append($job, "\nInstalling Docker...\n");
            $this->append($job, "Running: curl -fsSL https://get.docker.com | sh\n\n");

            $ssh->exec(
                'curl -fsSL https://get.docker.com | sh 2>&1',
                function (string $chunk) use ($job): void {
                    $this->append($job, $chunk);
                },
            );

            if ($ssh->getExitStatus() !== 0) {
                $this->fail($job, "\nDocker installation failed.\n");

                return Command::FAILURE;
            }

            $this->append($job, "\nDocker installation complete.\n");
            $this->checkDocker($ssh, $job);
        }

        $this->checkDockerCompose($ssh, $job);

        $job->status = SetupJobStatus::Completed;
        $this->setupJobRepository->update($job);

        return Command::SUCCESS;
    }

    private function ensureCurl(SSH2 $ssh, SetupJob $job): bool
    {
        $result = $ssh->exec('command -v curl 2>/dev/null');
        if ($ssh->getExitStatus() === 0 && is_string($result) && trim($result) !== '') {
            return true;
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

        return $ssh->getExitStatus() === 0;
    }

    private function checkDocker(SSH2 $ssh, SetupJob $job): bool
    {
        $this->append($job, "Checking Docker...\n");
        $result = $ssh->exec('docker --version 2>&1');

        if (is_string($result) && $ssh->getExitStatus() === 0 && str_contains($result, 'Docker version')) {
            $this->append($job, sprintf("  %s\n", $result));

            return true;
        }

        $this->append($job, "  Docker not installed.\n");

        return false;
    }

    private function checkDockerCompose(SSH2 $ssh, SetupJob $job): void
    {
        $this->append($job, "\nChecking Docker Compose...\n");
        $result = $ssh->exec('docker compose version 2>&1');

        if ($ssh->getExitStatus() === 0) {
            $this->append($job, sprintf("  %s\n", $result));
        } else {
            $this->append($job, "  Docker Compose plugin not available.\n");
        }
    }

    private function append(SetupJob $job, string $text): void
    {
        $this->setupJobRepository->appendOutput($job->id, $text);
    }

    private function fail(SetupJob $job, string $message): void
    {
        $this->setupJobRepository->appendOutput($job->id, $message);
        $job->status = SetupJobStatus::Failed;
        $this->setupJobRepository->update($job);
    }
}
