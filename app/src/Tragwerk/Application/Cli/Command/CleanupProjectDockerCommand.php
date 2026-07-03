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
use Tragwerk\Application\Exception\Credential\CredentialKeyEncryptionFailed;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;

use function assert;
use function escapeshellarg;
use function explode;
use function filter_var;
use function is_string;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

#[AsCommand(name: 'project:docker-cleanup')]
final class CleanupProjectDockerCommand extends Command
{
    public function __construct(
        private readonly CredentialRepository $credentialRepository,
        private readonly CredentialEncryptor $credentialEncryptor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project-id', InputArgument::REQUIRED, 'Project UUID')
            ->addArgument('project-slug', InputArgument::REQUIRED, 'Slugified project name')
            ->addArgument('host', InputArgument::REQUIRED, 'Primary server host')
            ->addArgument('port', InputArgument::REQUIRED, 'Primary server SSH port')
            ->addArgument('credential-id', InputArgument::REQUIRED, 'Credential UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId    = $input->getArgument('project-id');
        $projectSlug  = $input->getArgument('project-slug');
        $host         = $input->getArgument('host');
        $portRaw      = $input->getArgument('port');
        $credentialId = $input->getArgument('credential-id');

        assert(is_string($projectId));
        assert(is_string($projectSlug));
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
            $key = PublicKeyLoader::loadPrivateKey($this->credentialEncryptor->decrypt($credential->privateKey));
        } catch (NoKeyLoadedException | CredentialKeyEncryptionFailed $e) {
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

        $this->cleanupServer($sftp, $projectId, $projectSlug, $output);

        $output->writeln('[Cleanup] Done.');

        return Command::SUCCESS;
    }

    private function cleanupServer(
        SFTP $sftp,
        string $projectId,
        string $projectSlug,
        OutputInterface $output,
    ): void {
        $remoteBase = 'tragwerk/' . $projectId;

        $shortId = substr($projectId, 0, 8);
        $lsOut   = trim((string) $sftp->exec('ls ' . escapeshellarg($remoteBase) . ' 2>/dev/null'));

        if ($lsOut !== '') {
            foreach (explode("\n", $lsOut) as $branch) {
                $branch = trim($branch);
                if ($branch === '') {
                    continue;
                }

                $branchDir      = $remoteBase . '/' . $branch;
                $branchSlug     = $this->slugify($branch);
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
            }
        }

        $sftp->exec('rm -rf ' . escapeshellarg($remoteBase) . ' 2>/dev/null; true');
        $output->writeln('[Cleanup] Removed project directory.');

        $otherContainers = (int) trim((string) $sftp->exec(
            'docker network inspect tragwerk-net --format \'{{range .Containers}}{{.Name}} {{end}}\' 2>/dev/null'
            . ' | tr \' \' \'\\n\' | grep -v \'^tragwerk-traefik$\' | grep -v \'^$\' | wc -l',
        ));

        if ($otherContainers > 0) {
            $output->writeln('[Cleanup] Other projects still running on this server — Traefik kept.');

            return;
        }

        $sftp->exec('docker rm -f tragwerk-traefik 2>/dev/null; true');
        $sftp->exec('docker volume rm tragwerk-traefik-certs 2>/dev/null; true');
        $sftp->exec('docker network rm tragwerk-net 2>/dev/null; true');

        $output->writeln('[Cleanup] Traefik and shared network removed.');
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
