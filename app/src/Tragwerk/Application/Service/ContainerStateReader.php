<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;
use function basename;
use function escapeshellarg;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

final readonly class ContainerStateReader
{
    public function __construct(
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
    ) {
    }

    /**
     * Reads the live state of the environment's compose-managed services via SSH
     * (`docker compose ps -a`), including stopped/exited ones.
     *
     * @return array<string, array{state: string, health: string}> compose service name → state/health
     *
     * @throws RuntimeException on missing credential or SSH failure.
     */
    public function read(Project $project, string $branch): array
    {
        $server = $this->serverRepository->getById($project->serverId);
        assert($server instanceof Server);

        if ($server->credentialId === null) {
            throw new RuntimeException('No credential assigned to server.');
        }

        $credential = $this->credentialRepository->getById($server->credentialId);
        assert($credential instanceof Credential);

        if ($credential->privateKey === null) {
            throw new RuntimeException('Credential has no SSH key.');
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            throw new RuntimeException('Failed to load SSH key: ' . $e->getMessage(), previous: $e);
        }

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            throw new RuntimeException('SSH login failed.');
        }

        $sftp->setTimeout(30);

        $remoteDir      = 'tragwerk/' . $project->id->toString() . '/' . $branch;
        $composeProject = 'tw-' . substr($project->id->toString(), 0, 8) . '-' . $this->slugify(basename($branch));
        $dc             = 'docker compose --project-name ' . escapeshellarg($composeProject);

        $raw    = $sftp->exec('cd ~/' . $remoteDir . ' && ' . $dc . ' ps -a --format json 2>&1');
        $output = trim(is_string($raw) ? $raw : '');

        return $this->parseStates($output);
    }

    /** @return array<string, array{state: string, health: string}> */
    private function parseStates(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $states = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (! is_array($obj) || ! is_string($obj['Service'] ?? null) || $obj['Service'] === '') {
                continue;
            }

            $states[$obj['Service']] = [
                'state'  => is_string($obj['State'] ?? null) ? $obj['State'] : '',
                'health' => is_string($obj['Health'] ?? null) ? $obj['Health'] : '',
            ];
        }

        return $states;
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
