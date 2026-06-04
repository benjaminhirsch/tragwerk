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
use function preg_replace;
use function strtolower;
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

        $lsOut = trim((string) $sftp->exec('ls ' . escapeshellarg($remoteBase) . ' 2>/dev/null'));

        if ($lsOut !== '') {
            foreach (explode("\n", $lsOut) as $branch) {
                $branch = trim($branch);
                if ($branch === '') {
                    continue;
                }

                $branchDir  = $remoteBase . '/' . $branch;
                $branchSlug = $this->slugify($branch);

                $output->writeln('[Cleanup] Stopping containers for branch: ' . $branch);

                // Remove compose-managed containers + their declared volumes
                $sftp->exec(
                    'cd ' . escapeshellarg($branchDir)
                    . ' && NO_COLOR=1 docker compose -f docker-compose.yml down --volumes --remove-orphans 2>&1'
                    . '; true',
                );

                // Remove blue/green standalone containers (docker create, not compose up)
                $sftp->exec(
                    'docker ps -aq --filter ' . escapeshellarg('name=' . $branchSlug . '-')
                    . ' 2>/dev/null | xargs -r docker rm -f 2>/dev/null; true',
                );

                // Remove any leftover named volumes for this branch
                $sftp->exec(
                    'docker volume ls -q --filter ' . escapeshellarg('name=' . $branchSlug . '_')
                    . ' 2>/dev/null | xargs -r docker volume rm 2>/dev/null; true',
                );
            }
        }

        $sftp->exec('rm -rf ' . escapeshellarg($remoteBase) . ' 2>/dev/null; true');
        $output->writeln('[Cleanup] Removed project directory.');

        if ($swarmEnabled) {
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

                $sftp->exec(
                    'for i in $(seq 1 20); do'
                    . ' docker stack ls 2>/dev/null | grep -q "^' . $projectSlug . '-" || break;'
                    . ' sleep 2;'
                    . ' done',
                );
            }

            $sftp->exec('docker stack rm tragwerk-infra 2>/dev/null; true');
            $sftp->exec(
                'for i in $(seq 1 20); do'
                . ' docker stack ls 2>/dev/null | grep -q "^tragwerk-infra" || break;'
                . ' sleep 2;'
                . ' done',
            );

            $sftp->exec('docker swarm leave --force 2>/dev/null; true');

            $output->writeln('[Cleanup] Swarm infrastructure removed.');
        }

        // Remove Traefik container + certs volume — this server is now fully free
        $sftp->exec('docker rm -f tragwerk-traefik 2>/dev/null; true');
        $sftp->exec('docker volume rm tragwerk-traefik-certs 2>/dev/null; true');

        // Remove shared network — safe since this server had exactly one project
        $sftp->exec('docker network rm tragwerk-net 2>/dev/null; true');

        $output->writeln('[Cleanup] Traefik and shared network removed.');
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
