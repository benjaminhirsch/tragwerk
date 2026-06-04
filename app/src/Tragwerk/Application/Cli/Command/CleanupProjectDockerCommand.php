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
use function escapeshellarg;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function trim;

#[AsCommand(name: 'project:docker-cleanup')]
final class CleanupProjectDockerCommand extends Command
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
            ->addArgument('project-slug', InputArgument::REQUIRED, 'Slugified project name')
            ->addArgument('host', InputArgument::REQUIRED, 'Primary server host')
            ->addArgument('port', InputArgument::REQUIRED, 'Primary server SSH port')
            ->addArgument('credential-id', InputArgument::REQUIRED, 'Credential UUID')
            ->addArgument('swarm-enabled', InputArgument::REQUIRED, '1 if project used swarm, 0 otherwise')
            ->addArgument('swarm-nodes-json', InputArgument::OPTIONAL, 'JSON array of swarm node {host,port}', '[]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId    = $input->getArgument('project-id');
        $projectSlug  = $input->getArgument('project-slug');
        $host         = $input->getArgument('host');
        $portRaw      = $input->getArgument('port');
        $credentialId = $input->getArgument('credential-id');
        $swarmRaw     = $input->getArgument('swarm-enabled');
        $nodesJsonRaw = $input->getArgument('swarm-nodes-json');

        assert(is_string($projectId));
        assert(is_string($projectSlug));
        assert(is_string($host));
        assert(is_string($portRaw));
        assert(is_string($credentialId));
        assert(is_string($swarmRaw));
        assert(is_string($nodesJsonRaw));

        $port         = (int) $portRaw;
        $swarmEnabled = $swarmRaw === '1';
        $nodesRaw     = json_decode($nodesJsonRaw, true);
        $swarmNodes   = is_array($nodesRaw) ? $nodesRaw : [];

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

        $sftp = new SFTP($host, $port, 30);
        $sftp->setTimeout(0);

        if (! $sftp->login($credential->username, $key)) {
            $output->writeln('[Cleanup] SSH login failed.');

            return Command::FAILURE;
        }

        $output->writeln('[Cleanup] Connected to ' . $host . '.');

        $this->cleanupPrimary($sftp, $projectId, $projectSlug, $swarmEnabled, $output);

        foreach ($swarmNodes as $node) {
            if (! is_array($node) || ! is_string($node['host'] ?? null)) {
                continue;
            }

            $nodePort = is_int($node['port'] ?? null) ? $node['port'] : 22;
            $nodeSftp = new SFTP($node['host'], $nodePort, 30);
            $nodeSftp->setTimeout(0);

            if (! $nodeSftp->login($credential->username, $key)) {
                $output->writeln('[Cleanup] SSH login failed for swarm node ' . $node['host'] . '.');
                continue;
            }

            $nodeSftp->exec('rm -rf ' . escapeshellarg('tragwerk/' . $projectId) . ' 2>/dev/null; true');
            $output->writeln('[Cleanup] Removed project dir on swarm node ' . $node['host'] . '.');
        }

        $output->writeln('[Cleanup] Done.');

        return Command::SUCCESS;
    }

    private function cleanupPrimary(
        SFTP $sftp,
        string $projectId,
        string $projectSlug,
        bool $swarmEnabled,
        OutputInterface $output,
    ): void {
        $remoteBase = 'tragwerk/' . $projectId;

        // Find all branch dirs and run compose down for each
        $lsOut = trim((string) $sftp->exec('ls ' . escapeshellarg($remoteBase) . ' 2>/dev/null'));

        if ($lsOut !== '') {
            foreach (explode("\n", $lsOut) as $branch) {
                $branch = trim($branch);
                if ($branch === '') {
                    continue;
                }

                $branchDir = $remoteBase . '/' . $branch;
                $output->writeln('[Cleanup] Stopping containers for branch: ' . $branch);
                $sftp->exec(
                    'cd ' . escapeshellarg($branchDir)
                    . ' && NO_COLOR=1 docker compose -f docker-compose.yml down --volumes --remove-orphans 2>&1'
                    . '; true',
                );
            }
        }

        $sftp->exec('rm -rf ' . escapeshellarg($remoteBase) . ' 2>/dev/null; true');
        $output->writeln('[Cleanup] Removed project directory.');

        if (! $swarmEnabled) {
            return;
        }

        // Remove any remaining swarm stacks for this project
        $stacks = trim((string) $sftp->exec(
            'docker stack ls --format "{{.Name}}" 2>/dev/null | grep "^' . $projectSlug . '-" || true',
        ));

        if ($stacks !== '') {
            foreach (explode("\n", $stacks) as $stack) {
                $stack = trim($stack);
                if ($stack === '') {
                    continue;
                }

                $sftp->exec('docker stack rm ' . escapeshellarg($stack) . ' 2>/dev/null; true');
                $output->writeln('[Cleanup] Removed swarm stack: ' . $stack);
            }

            // Wait for stack removal
            $sftp->exec(
                'for i in $(seq 1 20); do'
                . ' docker stack ls 2>/dev/null | grep -q "^' . $projectSlug . '-" || break;'
                . ' sleep 2;'
                . ' done',
            );
        }

        // Remove swarm infra stack and leave swarm
        $sftp->exec('docker stack rm tragwerk-infra 2>/dev/null; true');
        $sftp->exec(
            'for i in $(seq 1 20); do'
            . ' docker stack ls 2>/dev/null | grep -q "^tragwerk-infra" || break;'
            . ' sleep 2;'
            . ' done',
        );

        $sftp->exec('docker swarm leave --force 2>/dev/null; true');
        $sftp->exec('docker rm -f tragwerk-traefik 2>/dev/null; true');

        $output->writeln('[Cleanup] Swarm infrastructure removed.');
    }
}
