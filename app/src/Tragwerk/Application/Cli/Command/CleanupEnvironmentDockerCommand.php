<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;

use function assert;
use function basename;
use function escapeshellarg;
use function filter_var;
use function is_string;
use function preg_replace;
use function strtolower;
use function substr;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

#[AsCommand(name: 'environment:docker-cleanup')]
final class CleanupEnvironmentDockerCommand extends Command
{
    public function __construct(
        private readonly CredentialRepository $credentialRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project-id', InputArgument::REQUIRED, 'Project UUID')
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch / environment name')
            ->addArgument('host', InputArgument::REQUIRED, 'Primary server host')
            ->addArgument('port', InputArgument::REQUIRED, 'Primary server SSH port')
            ->addArgument('credential-id', InputArgument::REQUIRED, 'Credential UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId    = $input->getArgument('project-id');
        $branch       = $input->getArgument('branch');
        $host         = $input->getArgument('host');
        $portRaw      = $input->getArgument('port');
        $credentialId = $input->getArgument('credential-id');

        assert(is_string($projectId));
        assert(is_string($branch));
        assert(is_string($host));
        assert(is_string($portRaw));
        assert(is_string($credentialId));

        $port = (int) $portRaw;

        try {
            $credential = $this->credentialRepository->getById(CredentialIdentifier::fromString($credentialId));
        } catch (Throwable $e) {
            $output->writeln('[Cleanup] Credential not found: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($credential->privateKey === null) {
            $output->writeln('[Cleanup] No private key on credential.');

            return Command::FAILURE;
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            $output->writeln('[Cleanup] Failed to load private key: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $formattedHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '[' . $host . ']'
            : $host;
        $sftp          = new SFTP($formattedHost, $port, 30);
        $sftp->setTimeout(0);

        if (! $sftp->login($credential->username, $key)) {
            $output->writeln('[Cleanup] SSH login failed.');

            return Command::FAILURE;
        }

        $output->writeln('[Cleanup] Connected to ' . $host . '.');

        $this->cleanupEnvironment($sftp, $projectId, $branch, $output);

        $output->writeln('[Cleanup] Done.');

        return Command::SUCCESS;
    }

    private function cleanupEnvironment(
        SFTP $sftp,
        string $projectId,
        string $branch,
        OutputInterface $output,
    ): void {
        $branchDir      = 'tragwerk/' . $projectId . '/' . $branch;
        $shortId        = substr($projectId, 0, 8);
        $branchSlug     = $this->slugify(basename($branch));
        $composeProject = 'tw-' . $shortId . '-' . $branchSlug;

        $output->writeln('[Cleanup] Stopping containers for branch: ' . $branch);

        $sftp->exec(
            'cd ' . escapeshellarg($branchDir)
            . ' && NO_COLOR=1 docker compose --project-name ' . escapeshellarg($composeProject)
            . ' -f docker-compose.yml down --volumes --remove-orphans 2>&1; true',
        );

        $sftp->exec(
            'docker ps -aq --filter ' . escapeshellarg('name=' . $composeProject . '-')
            . ' 2>/dev/null | xargs -r docker rm -f 2>/dev/null; true',
        );

        $sftp->exec(
            'docker volume ls -q --filter ' . escapeshellarg('name=' . $composeProject . '_')
            . ' 2>/dev/null | xargs -r docker volume rm 2>/dev/null; true',
        );

        $sftp->exec('rm -rf ' . escapeshellarg($branchDir) . ' 2>/dev/null; true');

        $output->writeln('[Cleanup] Removed environment directory. Traefik and shared network left intact.');
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
